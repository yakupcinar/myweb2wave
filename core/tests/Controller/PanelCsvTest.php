<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Atif\Ziyaretler;
use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * R2 (A29): panel tablolarının CSV dosyası — o anki süzgeçle, sayfasız,
 * e-posta maskeli. UTF-8 BOM + virgül; formül gibi başlayan hücre "'" ile
 * (CSV injection). Dosya bağımsız ayrıştırıcıyla (fgetcsv) okunur.
 */
final class PanelCsvTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE subscriptions, odeme_faturalari, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_cevaplari, huni_metin_cevaplari CASCADE');
    }

    public function testGirissizGirisSayfasina(): void
    {
        foreach (['/panel/api/kullanicilar.csv', '/panel/api/rapor/ziyaretler.csv', '/panel/api/rapor/cevaplar.csv'] as $adres) {
            $this->client->request('GET', $adres);
            self::assertResponseRedirects('/panel/giris', null, $adres);
        }
    }

    /** Aboneler: listenin süzgeci, sayfa boyutundan (25) fazla satır, maskeli e-posta, evet/hayır. */
    public function testAbonelerSuzgecliSayfasizMaskeli(): void
    {
        for ($i = 1; $i <= 30; ++$i) {
            $this->db->insert('subscriptions', ['stripe_subscription_id' => sprintf('sub_%02d', $i), 'stripe_customer_id' => 'cus_x', 'status' => 'active',
                'email' => "kisi{$i}@example.com", 'app' => 'keepchat', 'updated_at' => sprintf('2026-11-01 10:%02d:00Z', $i)]);
        }
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_bitti', 'stripe_customer_id' => 'cus_y', 'status' => 'canceled', 'email' => 'b@example.com', 'app' => 'keepchat']);
        $this->db->executeStatement("UPDATE subscriptions SET iptal_donem_sonu = true, donem_sonu = '2026-12-01 10:00Z' WHERE stripe_subscription_id = 'sub_30'");
        $this->giris();

        $this->client->request('GET', '/panel/api/kullanicilar.csv?durum=active');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/kullanicilar.csv', $this->client->getResponse());
        self::assertSame('attachment; filename=aboneler-'.date('Y-m-d').'.csv', $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $satirlar = $this->csv();

        self::assertSame(['abonelik', 'uygulama', 'durum', 'e-posta', 'güncelleme', 'ödeme', 'kaynak', 'ortam', 'kampanya', 'huni', 'reklam',
            'uygulamaya bağlı', 'iptal planlı', 'dönem sonu', 'duraklatıldı', 'duraklatma bitişi', 'son fatura para birimi', 'son fatura tutarı', 'son fatura iadesi'], $satirlar[0]);
        self::assertCount(31, $satirlar, 'başlık + 30 aktif; bitmiş süzgeçte elendi; sayfa boyutu yok');
        // Varsayılan sıra listeyle aynı: en yeni güncellenen önce.
        self::assertSame(['sub_30', 'keepchat', 'active', 'k***@example.com', '2026-11-01T10:30:00+00:00', '', '', '', '', '', 'hayır',
            'hayır', 'evet', '2026-12-01T10:00:00+00:00', 'hayır', '', '', '', ''], $satirlar[1]);
        self::assertNotContains('kisi30@example.com', array_merge(...$satirlar));
    }

    /** Ziyaretler: raporun dönemi ve süzgeci, ziyaret kimliği; kampanya adındaki formül çalışmaz. */
    public function testZiyaretlerSuzgecVeFormulKorumasi(): void
    {
        [$meta, $tiktok] = $this->ziyaretler();
        $this->giris();

        $this->client->request('GET', '/panel/api/rapor/ziyaretler.csv?kaynak=meta');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor/ziyaretler.csv', $this->client->getResponse());
        $satirlar = $this->csv();

        self::assertSame(['ziyaret', 'zaman', 'uygulama', 'huni', 'kaynak', 'kampanya', 'reklam', 'aşama', 'durum', 'e-posta'], $satirlar[0]);
        self::assertCount(2, $satirlar);
        self::assertSame([$meta, 'keepchat', 'genel', 'meta', "'=HYPERLINK(\"http://x\")", 'sayfayı gördü', '', ''],
            [$satirlar[1][0], $satirlar[1][2], $satirlar[1][3], $satirlar[1][4], $satirlar[1][5], $satirlar[1][7], $satirlar[1][8], $satirlar[1][9]]);
        self::assertNotContains($tiktok, array_column($satirlar, 0));

        // Sayfasız: panelin sayfa boyutundan (25) fazlası da gelir.
        $z = static::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $z);
        for ($i = 0; $i < 26; ++$i) {
            $z->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1);
        }
        $this->client->request('GET', '/panel/api/rapor/ziyaretler.csv');
        self::assertCount(1 + 28, $this->csv());

        $this->client->request('GET', '/panel/api/rapor/ziyaretler.csv?kaynak='.str_repeat('x', 300));
        self::assertResponseStatusCodeSame(422);
    }

    /** Cevaplar: seçenek (çoklu seçim ayrı satır) ve serbest metin; satır sonu ve tırnak hücrede kalır. */
    public function testCevaplarSecenekVeMetin(): void
    {
        [$meta, $tiktok] = $this->ziyaretler();
        $this->db->insert('huni_cevaplari', ['ziyaret_id' => $meta, 'soru' => 'hedef', 'secenek' => 'kilo']);
        $this->db->insert('huni_cevaplari', ['ziyaret_id' => $meta, 'soru' => 'hedef', 'secenek' => 'uyku']);
        $this->db->insert('huni_metin_cevaplari', ['ziyaret_id' => $meta, 'soru' => 'neden', 'cevap' => "-5 kilo \"hemen\"\nikinci satır"]);
        $this->db->insert('huni_cevaplari', ['ziyaret_id' => $tiktok, 'soru' => 'hedef', 'secenek' => '@komut']);
        $this->giris();

        $this->client->request('GET', '/panel/api/rapor/cevaplar.csv');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor/cevaplar.csv', $this->client->getResponse());
        $satirlar = $this->csv();

        self::assertSame(['ziyaret', 'zaman', 'uygulama', 'huni', 'soru', 'tür', 'cevap'], $satirlar[0]);
        $ozet = array_map(static fn (array $s): array => [$s[0], $s[3], $s[4], $s[5], $s[6]], \array_slice($satirlar, 1));
        usort($ozet, static fn (array $a, array $b): int => [$a[0] === $meta ? 0 : 1, $a[2], $a[4]] <=> [$b[0] === $meta ? 0 : 1, $b[2], $b[4]]);
        self::assertSame([
            [$meta, 'genel', 'hedef', 'seçenek', 'kilo'],
            [$meta, 'genel', 'hedef', 'seçenek', 'uyku'],
            [$meta, 'genel', 'neden', 'metin', "'-5 kilo \"hemen\"\nikinci satır"],
            [$tiktok, 'fitness', 'hedef', 'seçenek', "'@komut"],
        ], $ozet);

        $this->client->request('GET', '/panel/api/rapor/cevaplar.csv?kaynak=tiktok');
        self::assertSame([$tiktok], array_values(array_unique(array_column(\array_slice($this->csv(), 1), 0))));
    }

    /** @return array{string, string} meta ve tiktok ziyaretlerinin kimliği */
    private function ziyaretler(): array
    {
        $huniler = static::getContainer()->get(Huniler::class);
        $z = static::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertInstanceOf(Ziyaretler::class, $z);
        foreach (['genel', 'fitness'] as $ad) {
            self::assertSame([], $huniler->yeniHuni('keepchat', $ad, null, 'fitness'));
            self::assertIsInt($huniler->yayinla('keepchat', $ad));
        }
        $bos = array_fill_keys(Ziyaretler::ALANLAR, null);

        return [
            $z->kaydet('keepchat', ['utm_source' => 'meta', 'utm_campaign' => '=HYPERLINK("http://x")'] + $bos, 'genel', 1),
            $z->kaydet('keepchat', ['utm_source' => 'tiktok'] + $bos, 'fitness', 1),
        ];
    }

    /** @return list<list<string>> BOM'suz, bağımsız ayrıştırıcıyla */
    private function csv(): array
    {
        $icerik = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\u{FEFF}", $icerik, 'UTF-8 BOM');
        self::assertStringStartsWith('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $f = fopen('php://memory', 'r+');
        self::assertNotFalse($f);
        fwrite($f, substr($icerik, 3));
        rewind($f);
        $satirlar = [];
        while (false !== ($s = fgetcsv($f, null, ',', '"', ''))) {
            $satirlar[] = array_map(static fn (?string $h): string => (string) $h, $s);
        }

        return $satirlar;
    }

    private function giris(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
