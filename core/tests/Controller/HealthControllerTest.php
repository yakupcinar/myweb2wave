<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /health, üretimde "hangi sürüm çalışıyor ve veritabanına ulaşıyor mu"
 * sorusunun ölçüm noktası (CLAUDE.md §1). Sabit "ok" dönen bir uç, ölçtüğü
 * şeyi değil kendini raporlar (tuzak §11) — bu yüzden DB gerçekten sorgulanır.
 */
final class HealthControllerTest extends WebTestCase
{
    /** @var array{0: mixed, 1: mixed} */
    private array $eskiDbAdresi;

    protected function setUp(): void
    {
        $this->eskiDbAdresi = [$_SERVER['DATABASE_URL'] ?? null, $_ENV['DATABASE_URL'] ?? null];
    }

    protected function tearDown(): void
    {
        // 503 testi DB adresini bozuyor; geri konmazsa SONRAKİ her DB testi
        // port 1'e bağlanır (tuzak §13 — süit tek DB testiyle yeşil görünüyordu).
        [$_SERVER['DATABASE_URL'], $_ENV['DATABASE_URL']] = $this->eskiDbAdresi;
        parent::tearDown();
    }

    public function testVeritabaniAyaktaykenSurumuVeDbDurumunuDoner(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['status' => 'ok', 'db' => 'ok', 'version' => 'test-surumu'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testVeritabaniyokken503Doner(): void
    {
        // Yük dengeleyici/izleme ancak durum koduyla fark eder; gövde yetmez.
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'postgresql://app:app@127.0.0.1:1/app?serverVersion=18&charset=utf8';
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(503);
        self::assertSame(
            ['status' => 'degraded', 'db' => 'down', 'version' => 'test-surumu'],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }
}
