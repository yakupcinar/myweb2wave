<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use App\Atif\Ziyaretler;
use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * R1a: raporun bütün bölümleri aynı süzgeçle — huni, kaynak (Kaynaklar
 * sütunuyla aynı tanım), kampanya, ülke, deney (+ varyant); birlikte VE.
 * Önceki dönem ve sayfalı listeler de süzülür. Seçenekler dönemdeki
 * değerlerden. Beklenenler elle sayıldı (tuzak §3).
 *
 * Ziyaretler (30 gün içinde):
 *   v1 genel   meta/ekim     TR  d1=a  izin verdi
 *   v2 genel   (doğrudan)    DE
 *   v3 fitness meta/kasim    TR  d1=b
 *   v4 fitness tiktok/ekim   —
 *   v5 fitness meta/ekim     TR  — 35 gün önce (önceki dönem)
 */
final class RaporSuzgeciTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari CASCADE');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        foreach (['genel', 'fitness'] as $ad) {
            self::assertSame([], $huniler->yeniHuni('keepchat', $ad, null, 'fitness'));
            self::assertIsInt($huniler->yayinla('keepchat', $ad));
        }
        $z = static::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $z);
        $bos = array_fill_keys(Ziyaretler::ALANLAR, null);
        $v = [
            $z->kaydet('keepchat', ['utm_source' => 'meta', 'utm_campaign' => 'ekim'] + $bos, 'genel', 1, true, 'TR'),
            $z->kaydet('keepchat', $bos, 'genel', 1, null, 'DE'),
            $z->kaydet('keepchat', ['utm_source' => 'meta', 'utm_campaign' => 'kasim'] + $bos, 'fitness', 1, null, 'TR'),
            $z->kaydet('keepchat', ['utm_source' => 'tiktok', 'utm_campaign' => 'ekim'] + $bos, 'fitness', 1, null, null),
            $z->kaydet('keepchat', ['utm_source' => 'meta', 'utm_campaign' => 'ekim'] + $bos, 'fitness', 1, null, 'TR'),
        ];
        $this->db->executeStatement("UPDATE huni_ziyaretleri SET created_at = now() - interval '35 days' WHERE id = ?", [$v[4]]);
        $this->db->insert('ziyaret_deneyleri', ['ziyaret_id' => $v[0], 'deney' => 'd1', 'varyant' => 'a']);
        $this->db->insert('ziyaret_deneyleri', ['ziyaret_id' => $v[2], 'deney' => 'd1', 'varyant' => 'b']);
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    public function testHerSuzgecTekBasinaVeBirlikte(): void
    {
        $ziyaret = fn (array $suzgec): int => self::ziyaretToplami($this->rapor($suzgec)['sayimlar'] ?? null);
        self::assertSame(4, $ziyaret([]));
        self::assertSame(2, $ziyaret(['huni' => 'genel']));
        self::assertSame(2, $ziyaret(['kaynak' => 'meta']));
        self::assertSame(1, $ziyaret(['kaynak' => 'doğrudan']), 'Kaynaklar sütunuyla aynı tanım');
        self::assertSame(2, $ziyaret(['kampanya' => 'ekim']));
        self::assertSame(2, $ziyaret(['ulke' => 'TR']));
        self::assertSame(2, $ziyaret(['deney' => 'd1']));
        self::assertSame(1, $ziyaret(['deney' => 'd1', 'varyant' => 'b']));
        self::assertSame(1, $ziyaret(['huni' => 'fitness', 'kampanya' => 'ekim']), 've');
        self::assertSame(0, $ziyaret(['huni' => 'genel', 'ulke' => 'FR']));
    }

    public function testOncekiDonemGunlukIzinVeListelerDeSuzulur(): void
    {
        self::assertSame(1, self::ziyaretToplami(self::alan($this->rapor(['huni' => 'fitness']), 'onceki')['sayimlar'] ?? null), 'v5 önceki dönemde');
        // Süzgeçsiz önceki dönemde v5 var (1); genel'de yok. v5 organik — iki sütun birlikte.
        self::assertSame(0, self::ziyaretToplami(self::alan($this->rapor(['huni' => 'genel']), 'onceki')['sayimlar'] ?? null), 'kırma S4 ilk koşuda tutmadı: yalnız reklam sütunu bakılıyordu');
        // v5'in olayı bugün tarihli (fikstür yalnız ziyareti geri aldı; olay deposu değişmez) — v5'siz süzgeç.
        $gunluk = self::alan($this->rapor(['huni' => 'genel']), 'gunluk');
        self::assertSame(2, array_sum(array_map(static fn (mixed $g): int => \is_array($g) ? self::sayi($g['ziyaret'] ?? null) : -1, $gunluk)), 'olay deposu da süzülür');
        self::assertSame(['kabul' => 1, 'red' => 0, 'secmeyen' => 1], $this->rapor(['huni' => 'genel'])['izinler']);

        $this->client->request('GET', '/panel/api/rapor/ziyaretler', ['huni' => 'genel']);
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor/ziyaretler', $this->client->getResponse());
        self::assertSame(2, $this->json()['toplam']);
        $this->client->request('GET', '/panel/api/rapor/kaynaklar', ['ulke' => 'TR']);
        $k = $this->json();
        self::assertSame(2, $k['toplam']);
        self::assertSame(['ekim', 'kasim'], array_map(static fn (mixed $s): mixed => \is_array($s) ? $s['kampanya'] ?? null : null, self::alan($k, 'satirlar')));
    }

    public function testSeceneklerDonemdekiDegerlerdenVeBicimsizDeger422(): void
    {
        $this->client->request('GET', '/panel/api/rapor/secenekler', ['gun' => 30]);
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor/secenekler', $this->client->getResponse());
        self::assertSame([
            'huniler' => ['fitness', 'genel'],
            'kaynaklar' => ['meta', 'doğrudan', 'tiktok'],
            'kampanyalar' => ['ekim', 'kasim'],
            'ulkeler' => ['TR', 'DE'],
            'deneyler' => [['deney' => 'd1', 'varyantlar' => ['a', 'b']]],
        ], $this->json());

        foreach ([['ulke' => 'tr'], ['varyant' => 'a'], ['huni' => 'Büyük']] as $bozuk) {
            $this->client->request('GET', '/panel/api/rapor', $bozuk);
            self::assertResponseStatusCodeSame(422);
            Sozlesme::yanitiDogrula('GET', '/panel/api/rapor', $this->client->getResponse());
        }
    }

    /**
     * @param array<mixed> $suzgec
     *
     * @return array<string, mixed>
     */
    private function rapor(array $suzgec): array
    {
        $this->client->request('GET', '/panel/api/rapor', $suzgec);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor', $this->client->getResponse());

        return $this->json();
    }

    /**
     * Her çağrı son yanıtı okur.
     *
     * @phpstan-impure
     *
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }

    /** Reklam + organik ziyaret; biçim bozuksa -1 (iddia düşer). */
    private static function ziyaretToplami(mixed $sayimlar): int
    {
        if (!\is_array($sayimlar) || !\is_array($sayimlar['reklam'] ?? null) || !\is_array($sayimlar['organik'] ?? null)) {
            return -1;
        }

        return self::sayi($sayimlar['reklam']['ziyaret'] ?? null) + self::sayi($sayimlar['organik']['ziyaret'] ?? null);
    }

    /**
     * @param array<string, mixed> $d
     *
     * @return array<mixed>
     */
    private static function alan(array $d, string $ad): array
    {
        $v = $d[$ad] ?? null;
        self::assertIsArray($v, $ad);

        return $v;
    }

    private static function sayi(mixed $d): int
    {
        return \is_int($d) ? $d : -1;
    }
}
