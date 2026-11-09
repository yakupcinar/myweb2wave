<?php

declare(strict_types=1);

namespace App\Tests\Atif;

use App\Atif\MetaHarcamalari;
use App\Tests\Support\SahteMetaInsights;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * M5d (A18): Meta kampanya harcaması (Ads Insights). Yanıt biçimi Meta'nın
 * başvuru sayfasından (ad-account/insights): data[] satırı campaign_id,
 * campaign_name, spend (ondalık metin), account_currency, date_start;
 * sayfalar paging.cursors.after + paging.next.
 */
final class MetaHarcamalariTest extends KernelTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE reklam_harcamalari');
        SahteMetaInsights::sifirla();
    }

    private function meta(string $hesap = 'act_111', string $anahtar = 'test_insights_token'): MetaHarcamalari
    {
        $http = self::getContainer()->get(HttpClientInterface::class);
        self::assertInstanceOf(HttpClientInterface::class, $http);

        return new MetaHarcamalari($this->db, $http, $hesap, $anahtar, 'v99.0');
    }

    /** @return array<string, string> */
    private static function satir(string $id, string $ad, string $gun, string $harcama, string $para = 'EUR'): array
    {
        return ['campaign_id' => $id, 'campaign_name' => $ad, 'spend' => $harcama, 'account_currency' => $para, 'date_start' => $gun, 'date_stop' => $gun];
    }

    /** @return list<array{string, string, string, int, string}> */
    private function tablo(): array
    {
        /** @var list<array{kampanya_id: string, kampanya_adi: string, gun: string, harcama_kucuk: int, para_birimi: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT kampanya_id, kampanya_adi, gun::text AS gun, harcama_kucuk, para_birimi FROM reklam_harcamalari ORDER BY kampanya_id, gun',
        );

        return array_map(static fn (array $s): array => [$s['kampanya_id'], $s['kampanya_adi'], $s['gun'], $s['harcama_kucuk'], $s['para_birimi']], $satirlar);
    }

    public function testIstekSayfalarVeYazim(): void
    {
        SahteMetaInsights::$sayfalar = [
            [200, ['data' => [self::satir('c1', 'ekim', '2026-11-08', '12.5'), self::satir('c1', 'ekim', '2026-11-09', '0.01')],
                'paging' => ['cursors' => ['before' => 'b1', 'after' => 'a1'], 'next' => 'https://graph.facebook.com/v99.0/act_111/insights?after=a1&access_token=gizli']]],
            [200, ['data' => [self::satir('c2', 'kasim', '2026-11-09', '1234', 'JPY'), ['campaign_id' => 'bozuk']],
                'paging' => ['cursors' => ['before' => 'b2', 'after' => 'a2']]]],
        ];

        self::assertSame(['satir' => 3, 'atlanan' => 1], $this->meta()->cek(7, new \DateTimeImmutable('2026-11-09')));

        self::assertSame([['c1', 'ekim', '2026-11-08', 1250, 'eur'], ['c1', 'ekim', '2026-11-09', 1, 'eur'], ['c2', 'kasim', '2026-11-09', 1234, 'jpy']], $this->tablo());
        self::assertCount(2, SahteMetaInsights::$sorgular, 'next olmayan sayfada durur');
        $ilk = SahteMetaInsights::$sorgular[0];
        self::assertSame('/v99.0/act_111/insights', $ilk['yol']);
        // until dahil (Meta: until gününün sonuna kadar) — 7 gün = 03 … 09.
        self::assertSame(['campaign', '1', '{"since":"2026-11-03","until":"2026-11-09"}', 'campaign_id,campaign_name,spend,account_currency'], [$ilk['level'] ?? null, $ilk['time_increment'] ?? null, $ilk['time_range'] ?? null, $ilk['fields'] ?? null]);
        self::assertArrayNotHasKey('after', $ilk);
        // İkinci sayfa imleçle, paging.next adresine gidilmeden.
        self::assertSame('a1', SahteMetaInsights::$sorgular[1]['after'] ?? null);
    }

    /** Meta yakın günleri düzeltir: aynı gün yeniden çekilince üzerine yazılır (ad da). */
    public function testAyniGunYenidenYazilir(): void
    {
        SahteMetaInsights::$sayfalar = [[200, ['data' => [self::satir('c1', 'ekim', '2026-11-09', '10')]]], [200, ['data' => [self::satir('c1', 'ekim-yeni', '2026-11-09', '11.99')]]]];
        $this->meta()->cek(1, new \DateTimeImmutable('2026-11-09'));
        $this->meta()->cek(1, new \DateTimeImmutable('2026-11-09'));

        self::assertSame([['c1', 'ekim-yeni', '2026-11-09', 1199, 'eur']], $this->tablo());
    }

    /** Hata: hiçbir şey yazılmaz, metinde anahtar yok; ayar eksikse kapalı ve istek yok. */
    public function testHataVeKapali(): void
    {
        SahteMetaInsights::$sayfalar = [
            [200, ['data' => [self::satir('c1', 'ekim', '2026-11-09', '10')], 'paging' => ['cursors' => ['after' => 'a1'], 'next' => 'x']]],
            [500, '{"error":{"message":"x"}}'],
        ];
        $hata = $this->meta()->cek(7);
        self::assertSame('Meta Insights HTTP 500', $hata);
        self::assertSame([], $this->tablo(), 'yarım çekiş yazılmaz');

        SahteMetaInsights::$agHatasi = 'bağlanamadı';
        $hata = $this->meta()->cek(7);
        self::assertIsString($hata);
        self::assertStringNotContainsString('test_insights_token', $hata);

        SahteMetaInsights::sifirla();
        self::assertIsString($this->meta('111')->cek(7));
        self::assertIsString($this->meta('act_111', '')->cek(7));
        self::assertSame([], SahteMetaInsights::$sorgular);
    }

    /** Tutar Meta'nın ondalık metninden; biçim dışı satır yazılmaz, sayılır. */
    public function testTutarBicimi(): void
    {
        SahteMetaInsights::$sayfalar = [[200, ['data' => [
            self::satir('a', 'a', '2026-11-09', '1.005'), self::satir('b', 'b', '2026-11-09', '0.29'), self::satir('c', 'c', '2026-11-09', '1234.5', 'JPY'),
            self::satir('d', 'd', '2026-11-09', '1e3'), self::satir('e', 'e', '2026-11-09', '-1'), self::satir('f', 'f', '2026-11-09', '1,5'),
        ]]]];

        self::assertSame(['satir' => 3, 'atlanan' => 3], $this->meta()->cek(1, new \DateTimeImmutable('2026-11-09')));
        self::assertSame([['a', 'a', '2026-11-09', 101, 'eur'], ['b', 'b', '2026-11-09', 29, 'eur'], ['c', 'c', '2026-11-09', 1235, 'jpy']], $this->tablo());
    }

    public function testKonsol(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:reklam-harcamalari:cek'));
        SahteMetaInsights::$sayfalar = [[200, ['data' => [self::satir('c1', 'ekim', '2026-11-09', '10')]]]];

        self::assertSame(Command::SUCCESS, $komut->execute(['--gun' => '3']));
        self::assertStringContainsString('Yazılan: 1', $komut->getDisplay());
        self::assertSame(Command::INVALID, $komut->execute(['--gun' => '91']));
        SahteMetaInsights::$sayfalar = [[403, '{}']];
        self::assertSame(Command::FAILURE, $komut->execute([]));
    }
}
