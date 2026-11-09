<?php

declare(strict_types=1);

namespace App\Tests\Olay;

use App\Atif\Ziyaretler;
use App\Billing\SubscriptionSync;
use App\Huni\Huniler;
use App\Olay\Olaylar;
use Doctrine\DBAL\Connection;
use Stripe\Event;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * T4: olay deposu — ziyaretin zaman çizelgesi ve uygulamanın olay akışı.
 * Zamanlar elle sabitlenir, beklenen sıra elle yazılır (tuzak §3).
 */
final class OlaylarTest extends KernelTestCase
{
    private Connection $db;
    private Ziyaretler $ziyaretler;
    private Olaylar $olaylar;
    private SubscriptionSync $sync;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, subscriptions, processed_webhook_events, reklam_donusumleri, revenuecat_gidenler CASCADE');
        $ziyaretler = self::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $ziyaretler);
        $this->ziyaretler = $ziyaretler;
        $this->olaylar = new Olaylar($db);
        $sync = self::getContainer()->get(SubscriptionSync::class);
        self::assertInstanceOf(SubscriptionSync::class, $sync);
        $this->sync = $sync;

        // Ziyaret huni/sürümü yalnız yayınlanmış çiftle yazılır.
        $huniler = self::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'kiminle', 'tur' => 'ekran', 'baslik' => 'Kiminle?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'aile', 'metin' => 'A'], ['id' => 'is', 'metin' => 'B']]]]],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));
    }

    /** Ziyaret → izin → iki adım + cevaplar → ödeme → abonelik; gerçek yazma yollarından (M5a). */
    private function tamZiyaret(string $app = 'keepchat'): string
    {
        $z = $this->ziyaretler->kaydet($app, array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, true);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is', 'aile']));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'Gizli metin'));
        self::assertTrue($this->ziyaretler->ilerle($z, 2, 'paywall', null));
        self::assertTrue($this->ziyaretler->oturumBagla($z, $app, 'cs_'.$z, null, null));
        $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'eur',
            'metadata' => ['app' => $app]]);
        $this->abonelik($z, 'active', 1_800_000_000, $app);

        return $z;
    }

    /** @param array<string, mixed> $nesne */
    private function stripe(string $tur, array $nesne, int $zaman = 1_800_000_000): void
    {
        $this->sync->apply(Event::constructFrom(['id' => 'evt_'.bin2hex(random_bytes(6)), 'object' => 'event', 'type' => $tur, 'created' => $zaman, 'data' => ['object' => $nesne]]));
    }

    private function abonelik(string $z, string $durum, int $zaman, string $app = 'keepchat'): void
    {
        $this->stripe('customer.subscription.updated', ['id' => 'sub_'.$z, 'object' => 'subscription', 'customer' => 'cus_1', 'status' => $durum, 'metadata' => ['app' => $app]], $zaman);
    }

    /** @return list<string> */
    private function adlar(string $z): array
    {
        return array_map(static fn (array $o): string => $o['ad'].([] === $o['veri'] ? '' : ' '.json_encode($o['veri'], \JSON_UNESCAPED_UNICODE)), $this->olaylar->ziyaretin($z));
    }

    public function testZiyaretinZamanCizelgesi(): void
    {
        $z = $this->tamZiyaret();

        // Sıra yazılış sırası (id) — neden önce, sonuç sonra.
        self::assertSame([
            'huni.ziyaret {"huni":"genel","surum":1}',
            'izin.verildi',
            'adim.goruldu {"adim":"kiminle","sira":0}',
            'cevap.verildi {"soru":"kiminle","secenekler":["aile","is"]}',
            'adim.goruldu {"adim":"not","sira":1}',
            // Serbest yanıt metni olaya girmez (Ç90) — yalnız hangi soru.
            'cevap.verildi {"soru":"not"}',
            'adim.goruldu {"adim":"paywall","sira":2}',
            'odeme.baslatildi',
            'odeme.tamamlandi',
            'abonelik.durum {"durum":"active"}',
        ], $this->adlar($z));
        $zamanlar = array_column($this->olaylar->ziyaretin($z), 'zaman');
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $zamanlar[0]);
        $sirali = $zamanlar;
        sort($sirali);
        self::assertSame($sirali, $zamanlar);
        self::assertStringNotContainsString('Gizli metin', (string) json_encode($this->olaylar->ziyaretin($z)));
    }

    /**
     * Ç92: geçmiş korunur — cevap değişimi, izin geri alma ve yeniden verme
     * ayrı olay; durum değişmeyen tekrar (aynı cevap, aynı adım, zaten izinli)
     * olay değil.
     */
    public function testGecmisKorunurTekrarOlayDegil(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['aile']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', []));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'a'));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'a'));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'b'));
        self::assertTrue($this->ziyaretler->izinVer($z, []));
        self::assertTrue($this->ziyaretler->izinVer($z, []));
        self::assertTrue($this->ziyaretler->izinGeriAl($z));
        self::assertTrue($this->ziyaretler->izinGeriAl($z));
        self::assertTrue($this->ziyaretler->izinVer($z, []));

        self::assertSame([
            'huni.ziyaret',
            'adim.goruldu {"adim":"kiminle","sira":0}',
            'cevap.verildi {"soru":"kiminle","secenekler":["aile"]}',
            'cevap.verildi {"soru":"kiminle","secenekler":["is"]}',
            'adim.goruldu {"adim":"not","sira":1}',
            'cevap.verildi {"soru":"not"}',
            'cevap.verildi {"soru":"not"}',
            'izin.verildi',
            'izin.reddedildi',
            'izin.verildi',
        ], $this->adlar($z));
    }

    /**
     * Ç92: abonelik durum geçmişi (active → past_due → canceled); aynı durum
     * tekrar ya da sıra dışı gelen eski olay (tuzak §13) olay değil.
     */
    public function testAbonelikDurumGecmisi(): void
    {
        $z = $this->tamZiyaret();
        $this->abonelik($z, 'active', 1_800_000_100);
        $this->abonelik($z, 'past_due', 1_800_000_200);
        $this->abonelik($z, 'active', 1_800_000_150);
        $this->abonelik($z, 'canceled', 1_800_000_300);

        self::assertSame(['active', 'past_due', 'canceled'], array_column(array_column(array_filter($this->olaylar->ziyaretin($z), static fn (array $o): bool => 'abonelik.durum' === $o['ad']), 'veri'), 'durum'));
    }

    /** Abonelik olayı ödeme oturumundan önce gelirse durum bağlanınca çizelgeye girer (bir kez). */
    public function testAbonelikOturumdanOnceGelirse(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, true);
        self::assertTrue($this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_'.$z, null, null));
        $this->abonelik($z, 'trialing', 1_800_000_000);
        // Aynı oturumun tamamlanması iki ayrı olay kimliğiyle gelse de tek olay.
        foreach ([1, 2] as $_) {
            $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
                'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 0, 'currency' => 'eur',
                'metadata' => ['app' => 'keepchat']]);
        }

        self::assertSame(['huni.ziyaret', 'izin.verildi', 'odeme.baslatildi', 'odeme.tamamlandi', 'abonelik.durum {"durum":"trialing"}'], $this->adlar($z));
    }

    /** Yalnız ekleme: geçmiş yeniden yazılamaz (DB kısıtı, uygulamadan bağımsız — tuzak §4). */
    public function testOlayGuncellenemez(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);

        $this->expectExceptionMessage('yalnız ekleme');
        $this->db->executeStatement("UPDATE olaylar SET ad = 'izin.verildi' WHERE ziyaret_id = :z", ['z' => $z]);
    }

    /**
     * Akış imleci bitmemiş işlemin olayını atlamaz: id işlem içinde alınır,
     * görünürlük commit'te. Yarım kalan işlemin (küçük id) arkasındaki biten
     * olay da beklenir; işlem bitince ikisi sırayla gelir.
     */
    public function testBitmemisIslemAkistaBeklenir(): void
    {
        $a = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $b = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $ilk = $this->olaylar->akis('keepchat', null, 10);
        self::assertNotNull($ilk);
        self::assertCount(2, $ilk['olaylar']);

        // Ayrı bağlantı = ayrı işlem (aynı test veritabanı).
        $ikinci = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $ikinci->beginTransaction();
        Olaylar::ekle($ikinci, $a, 'izin.verildi');
        Olaylar::ekle($this->db, $b, 'izin.reddedildi');

        $imlec = base64_encode(json_encode([$this->sonId() - 2], \JSON_THROW_ON_ERROR));
        self::assertSame([], $this->olaylar->akis('keepchat', $imlec, 10)['olaylar'] ?? null, 'yarım işlemin arkası da beklenir');
        $ikinci->commit();
        $ikinci->close();
        self::assertSame(
            [[$a, 'izin.verildi'], [$b, 'izin.reddedildi']],
            array_map(static fn (array $o): array => [$o['ziyaret'], $o['ad']], $this->olaylar->akis('keepchat', $imlec, 10)['olaylar'] ?? []),
        );
    }

    private function sonId(): int
    {
        $id = $this->db->fetchOne('SELECT max(id) FROM olaylar');
        self::assertIsNumeric($id);

        return (int) $id;
    }

    public function testIzniReddedenZiyaret(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, false);

        self::assertSame(['huni.ziyaret', 'izin.reddedildi'], array_column($this->olaylar->ziyaretin($z), 'ad'));
    }

    /** Akış imleçle sayfalanır: her olay tam bir kez, sırayla; başka uygulama yok. */
    public function testAkisImlecleSayfalanir(): void
    {
        $a = $this->tamZiyaret('keepchat');
        $b = $this->tamZiyaret('keepchat');
        $this->tamZiyaret('baska');

        $gorulen = [];
        $imlec = null;
        $sayfa = 0;
        do {
            $sonuc = $this->olaylar->akis('keepchat', $imlec, 3);
            self::assertNotNull($sonuc);
            ['olaylar' => $olaylar, 'imlec' => $imlec] = $sonuc;
            self::assertLessThanOrEqual(3, \count($olaylar));
            foreach ($olaylar as $o) {
                $gorulen[] = ($o['ziyaret'] === $a ? 'a ' : ($o['ziyaret'] === $b ? 'b ' : '? ')).$o['ad'];
            }
            self::assertLessThan(10, ++$sayfa);
        } while (null !== $imlec);

        $ziyaret = ['huni.ziyaret', 'izin.verildi', 'adim.goruldu', 'cevap.verildi', 'adim.goruldu', 'cevap.verildi', 'adim.goruldu', 'odeme.baslatildi', 'odeme.tamamlandi', 'abonelik.durum'];
        self::assertSame([
            ...array_map(static fn (string $ad): string => "a {$ad}", $ziyaret),
            ...array_map(static fn (string $ad): string => "b {$ad}", $ziyaret),
        ], $gorulen);
    }

    public function testAyniAndakiOlaylarAyrilir(): void
    {
        // Aynı işlemdeki olayların zamanı aynı (now()): ziyaret + izin tek işlemde.
        // İmleç yine her birini bir kez vermeli.
        $z = $this->tamZiyaret();
        self::assertSame(1, $this->db->fetchOne("SELECT count(DISTINCT zaman) FROM olaylar WHERE ziyaret_id = :z AND ad IN ('huni.ziyaret', 'izin.verildi')", ['z' => $z]));

        $hepsi = [];
        $imlec = null;
        do {
            $sonuc = $this->olaylar->akis('keepchat', $imlec, 1);
            self::assertNotNull($sonuc);
            ['olaylar' => $olaylar, 'imlec' => $imlec] = $sonuc;
            array_push($hepsi, ...$olaylar);
            // İmleç ilerlemezse sonsuz döngü yerine düşsün.
            self::assertLessThan(20, \count($hepsi));
        } while (null !== $imlec);

        self::assertCount(2, array_filter($hepsi, static fn (array $o): bool => 'cevap.verildi' === $o['ad']));
        self::assertCount(10, $hepsi);
    }

    public function testBozukImlecVeKimlik(): void
    {
        self::assertSame([], $this->olaylar->ziyaretin('x'));
        self::assertNull($this->olaylar->akis('keepchat', 'bozuk', 10));
    }

    /** Görünümün ürettiği her ad sözlükte (yeni olay türü sözlüğe yazılmadan eklenemez). */
    public function testHerOlaySozlukte(): void
    {
        $this->tamZiyaret();
        $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, false);

        $adlar = array_map(static fn (mixed $a): string => \is_string($a) ? $a : '', $this->db->fetchFirstColumn('SELECT DISTINCT ad FROM ziyaret_olaylari ORDER BY ad'));
        self::assertSame(array_keys(Olaylar::SOZLUK), array_values(array_intersect(array_keys(Olaylar::SOZLUK), $adlar)));
        self::assertSame([], array_values(array_diff($adlar, array_keys(Olaylar::SOZLUK))));
    }

    public function testKonsol(): void
    {
        $z = $this->tamZiyaret();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:olaylar'));

        self::assertSame(Command::SUCCESS, $komut->execute(['ziyaret' => $z]));
        $cikti = json_decode($komut->getDisplay(), true, 16, \JSON_THROW_ON_ERROR);
        self::assertIsArray($cikti);
        self::assertCount(10, $cikti);

        self::assertSame(Command::SUCCESS, $komut->execute(['--sozluk' => true]));
        self::assertStringContainsString('odeme.tamamlandi', $komut->getDisplay());
        self::assertSame(Command::INVALID, $komut->execute(['ziyaret' => 'x']));
    }
}
