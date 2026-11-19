<?php

declare(strict_types=1);

namespace App\Tests\Atif;

use App\Atif\MetaBaglantisi;
use App\Atif\TiktokBaglantisi;
use App\Atif\TiktokHarcamalari;
use App\Tests\Support\SahteMetaInsights;
use App\Tests\Support\SahteTiktok;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A31 (TB3): TikTok kampanya harcaması (Marketing API report/integrated/get).
 * Yanıt biçimi üçüncü taraf örneklerinden (Ç154): data.list[] satırı
 * dimensions {campaign_id, stat_time_day "YYYY-MM-DD HH:MM:SS"}, metrics
 * {spend (ondalık metin), campaign_name}; sayfalar data.page_info.total_page.
 * Para birimi satırda yok — advertiser/info'dan.
 */
final class TiktokHarcamalariTest extends KernelTestCase
{
    private const ANAHTAR = 'test_tiktok_rapor_0123456789';
    private const RAPOR = '/open_api/v1.3/report/integrated/get/';
    private const BILGI = '/open_api/v1.3/advertiser/info/';

    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE reklam_harcamalari, tiktok_baglantilari, meta_baglantilari');
        SahteTiktok::sifirla();
        SahteMetaInsights::sifirla();
    }

    /** Sayfalar birleşir; gün stat_time_day'in tarih kısmı; para birimi reklamverenden; sorgu TikTok'un beklediği biçimde. */
    public function testGunlukHarcamaVeSayfalar(): void
    {
        $this->paraBirimi('EUR');
        SahteTiktok::$yanitlar[self::RAPOR] = static fn (array $q): array => '1' === ($q['page'] ?? null)
            ? self::sayfa([self::satir('c1', 'ekim', '2026-11-08', '10.5'), self::satir('c2', 'kasim', '2026-11-09', '3')], 2)
            : self::sayfa([self::satir('c1', 'ekim', '2026-11-09', '0.25')], 2);

        self::assertSame(['satir' => 3, 'atlanan' => 0], $this->tiktok()->cek(3, new \DateTimeImmutable('2026-11-09')));
        self::assertSame([['c1', 'ekim', '2026-11-08', 1050, 'eur'], ['c1', 'ekim', '2026-11-09', 25, 'eur'], ['c2', 'kasim', '2026-11-09', 300, 'eur']], $this->tablo());

        $bilgi = SahteTiktok::$sorgular[0];
        self::assertSame([self::BILGI, '["7000000000001"]'], [$bilgi['yol'], $bilgi['advertiser_ids'] ?? null]);
        $rapor = SahteTiktok::$sorgular[1];
        self::assertSame(
            [self::RAPOR, '7000000000001', 'BASIC', 'AUCTION_CAMPAIGN', '["campaign_id","stat_time_day"]', '["spend","campaign_name"]', '2026-11-07', '2026-11-09', '1'],
            [$rapor['yol'], $rapor['advertiser_id'] ?? null, $rapor['report_type'] ?? null, $rapor['data_level'] ?? null, $rapor['dimensions'] ?? null, $rapor['metrics'] ?? null, $rapor['start_date'] ?? null, $rapor['end_date'] ?? null, $rapor['page'] ?? null],
        );
        self::assertIsArray($rapor['basliklar']);
        self::assertContains('Access-Token: '.self::ANAHTAR, $rapor['basliklar']);
        self::assertSame('2', SahteTiktok::$sorgular[2]['page'] ?? null);
        self::assertCount(3, SahteTiktok::$sorgular, 'son sayfadan sonra istek yok');

        // Yeniden çekiş üzerine yazar (TikTok yakın günleri düzeltir).
        SahteTiktok::$sorgular = [];
        SahteTiktok::$yanitlar[self::RAPOR] = self::sayfa([self::satir('c1', 'ekim-yeni', '2026-11-09', '1')], 1);
        self::assertSame(['satir' => 1, 'atlanan' => 0], $this->tiktok()->cek(1, new \DateTimeImmutable('2026-11-09')));
        self::assertSame(['c1', 'ekim-yeni', '2026-11-09', 100, 'eur'], $this->tablo()[1]);
    }

    /** TikTok gün boyutuyla tek istekte en çok 30 gün: 45 gün iki pencere, aralıksız ve çakışmasız. */
    public function testOtuzGunlukPencereler(): void
    {
        $this->paraBirimi('EUR');
        SahteTiktok::$yanitlar[self::RAPOR] = self::sayfa([], 1);

        self::assertSame(['satir' => 0, 'atlanan' => 0], $this->tiktok()->cek(45, new \DateTimeImmutable('2026-11-09')));
        self::assertSame([['2026-09-26', '2026-10-25'], ['2026-10-26', '2026-11-09']],
            array_map(static fn (array $s): array => [$s['start_date'] ?? null, $s['end_date'] ?? null], array_slice(SahteTiktok::$sorgular, 1)));
    }

    /** Tutar TikTok'un ondalık metninden, reklamverenin para biriminde; biçim dışı satır yazılmaz, sayılır. */
    public function testTutarBicimi(): void
    {
        $this->paraBirimi('JPY');
        $bozuk = self::satir('g', 'g', '2026-11-09', '1');
        unset($bozuk['metrics']['campaign_name']);
        SahteTiktok::$yanitlar[self::RAPOR] = self::sayfa([
            self::satir('a', 'a', '2026-11-09', '1234.5'), self::satir('b', 'b', '2026-11-09 00:00:00', '7'),
            self::satir('d', 'd', '2026-11-09', '1e3'), self::satir('e', 'e', '2026-11-09', '-1'), self::satir('f', 'f', '09.11.2026', '1'), $bozuk,
        ], 1);

        self::assertSame(['satir' => 2, 'atlanan' => 4], $this->tiktok()->cek(1, new \DateTimeImmutable('2026-11-09')));
        self::assertSame([['a', 'a', '2026-11-09', 1235, 'jpy'], ['b', 'b', '2026-11-09', 7, 'jpy']], $this->tablo());
    }

    /** Hata: hangi uygulama, anahtarsız mesaj, yarım çekiş yazılmaz; bağlantı eksikse kapalı ve istek yok. */
    public function testHataVeKapali(): void
    {
        $this->paraBirimi('EUR');
        SahteTiktok::$yanitlar[self::RAPOR] = static fn (array $q): array => '1' === ($q['page'] ?? null)
            ? self::sayfa([self::satir('c1', 'ekim', '2026-11-09', '10')], 2)
            : [200, ['code' => 40105, 'message' => 'Access token '.self::ANAHTAR.' is incorrect or has been revoked.']];

        self::assertSame('keepchat: TikTok reddetti: Access token … is incorrect or has been revoked. (kod 40105)', $this->tiktok()->cek(7));
        self::assertSame([], $this->tablo(), 'yarım çekiş yazılmaz');

        SahteTiktok::$yanitlar[self::BILGI] = [200, ['code' => 0, 'message' => 'OK', 'data' => ['list' => [[]]]]];
        self::assertSame('keepchat: TikTok reklamverenin para birimi okunamadı', $this->tiktok()->cek(7));

        SahteTiktok::$agHatasi = 'bağlanamadı';
        self::assertSame('keepchat: TikTok bağlantı veya yanıt hatası', $this->tiktok()->cek(7));

        SahteTiktok::sifirla();
        $this->db->executeStatement('TRUNCATE tiktok_baglantilari');
        self::assertIsString($this->tiktok('')->cek(7), 'reklamveren yok');
        $this->db->executeStatement('TRUNCATE tiktok_baglantilari');
        self::assertIsString($this->tiktok('7000000000001', '')->cek(7), 'anahtar yok');
        self::assertSame([], SahteTiktok::$sorgular);
    }

    /** Bağlı her uygulamanın reklamvereni kendi anahtarıyla çekilir. */
    public function testHerUygulamaninHesabi(): void
    {
        $this->tiktok('7000000000001');
        $this->tiktok('7000000000002', 'baska_tiktok_rapor_987654321', 'baska');
        $this->paraBirimi('EUR');
        SahteTiktok::$yanitlar[self::RAPOR] = static fn (array $q): array => self::sayfa([self::satir('c-'.(\is_string($q['advertiser_id'] ?? null) ? $q['advertiser_id'] : ''), 'x', '2026-11-09', '1')], 1);

        self::assertSame(['satir' => 2, 'atlanan' => 0], $this->tiktok()->cek(1, new \DateTimeImmutable('2026-11-09')));
        $raporlar = array_values(array_filter(SahteTiktok::$sorgular, static fn (array $s): bool => self::RAPOR === $s['yol']));
        self::assertSame([['7000000000002', true], ['7000000000001', true]], array_map(static fn (array $s): array => [
            $s['advertiser_id'] ?? null,
            \is_array($s['basliklar']) && \in_array('Access-Token: '.('7000000000002' === ($s['advertiser_id'] ?? null) ? 'baska_tiktok_rapor_987654321' : self::ANAHTAR), $s['basliklar'], true),
        ], $raporlar));
    }

    /** Komut bağlı her ağı çeker; biri hata verirse öteki yine yazılır, çıkış hata; hiçbiri bağlı değilse hata. */
    public function testKonsolIkiAg(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:reklam-harcamalari:cek'));

        self::assertSame(Command::FAILURE, $komut->execute([]));
        self::assertStringContainsString('Reklam harcaması kapalı', $komut->getDisplay());

        $this->tiktok();
        $this->paraBirimi('EUR');
        SahteTiktok::$yanitlar[self::RAPOR] = self::sayfa([self::satir('t1', 'ekim', (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d'), '2')], 1);
        self::assertSame(Command::SUCCESS, $komut->execute(['--gun' => '1']));
        self::assertStringContainsString('TikTok — Yazılan: 1', $komut->getDisplay());
        self::assertStringNotContainsString('Meta', $komut->getDisplay(), 'bağlı olmayan ağ denenmez');

        $meta = self::getContainer()->get(MetaBaglantisi::class);
        self::assertInstanceOf(MetaBaglantisi::class, $meta);
        self::assertSame([], $meta->kaydet('keepchat', ['reklamHesabi' => 'act_11111', 'insightsAnahtari' => 'test_insights_token_0123456789']));
        SahteMetaInsights::$sayfalar = [[200, ['data' => [['campaign_id' => 'm1', 'campaign_name' => 'ekim', 'spend' => '5', 'account_currency' => 'EUR', 'date_start' => '2026-11-09', 'date_stop' => '2026-11-09']]]]];
        SahteTiktok::$yanitlar[self::RAPOR] = [200, ['code' => 40105, 'message' => 'Access token is incorrect']];
        $this->db->executeStatement('TRUNCATE reklam_harcamalari');

        self::assertSame(Command::FAILURE, $komut->execute(['--gun' => '1']));
        self::assertStringContainsString('Meta — Yazılan: 1', $komut->getDisplay());
        self::assertStringContainsString('TikTok: keepchat: TikTok reddetti', $komut->getDisplay());
        self::assertSame(['meta'], $this->db->fetchFirstColumn('SELECT saglayici FROM reklam_harcamalari'), 'TikTok hatası Meta satırını düşürmez');
    }

    private function tiktok(string $reklamveren = '7000000000001', string $anahtar = self::ANAHTAR, string $app = 'keepchat'): TiktokHarcamalari
    {
        $baglanti = self::getContainer()->get(TiktokBaglantisi::class);
        self::assertInstanceOf(TiktokBaglantisi::class, $baglanti);
        self::assertSame([], $baglanti->kaydet($app, ['reklamveren' => $reklamveren, 'raporAnahtari' => '' === $anahtar ? null : $anahtar]));
        $t = self::getContainer()->get(TiktokHarcamalari::class);
        self::assertInstanceOf(TiktokHarcamalari::class, $t);

        return $t;
    }

    private function paraBirimi(string $para): void
    {
        SahteTiktok::$yanitlar[self::BILGI] = [200, ['code' => 0, 'message' => 'OK', 'data' => ['list' => [['currency' => $para, 'name' => 'web2quiz']]]]];
    }

    /**
     * @param list<array<string, array<string, string>>> $satirlar
     *
     * @return array{int, array<mixed>}
     */
    private static function sayfa(array $satirlar, int $toplamSayfa): array
    {
        return [200, ['code' => 0, 'message' => 'OK', 'data' => ['list' => $satirlar, 'page_info' => ['page' => 1, 'page_size' => 1000, 'total_page' => $toplamSayfa]]]];
    }

    /** @return array{dimensions: array<string, string>, metrics: array<string, string>} */
    private static function satir(string $id, string $ad, string $gun, string $harcama): array
    {
        return ['dimensions' => ['campaign_id' => $id, 'stat_time_day' => 10 === \strlen($gun) && '-' === $gun[4] ? "{$gun} 00:00:00" : $gun], 'metrics' => ['spend' => $harcama, 'campaign_name' => $ad]];
    }

    /** @return list<array{string, string, string, int, string}> */
    private function tablo(): array
    {
        /** @var list<array{kampanya_id: string, kampanya_adi: string, gun: string, harcama_kucuk: int, para_birimi: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT kampanya_id, kampanya_adi, gun::text AS gun, harcama_kucuk, para_birimi FROM reklam_harcamalari WHERE saglayici = 'tiktok' ORDER BY kampanya_id, gun",
        );

        return array_map(static fn (array $s): array => [$s['kampanya_id'], $s['kampanya_adi'], $s['gun'], $s['harcama_kucuk'], $s['para_birimi']], $satirlar);
    }
}
