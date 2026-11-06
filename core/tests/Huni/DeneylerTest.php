<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Deneyler;
use App\Ozellik\Ozellikler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** M4a: atama aynı tohumda değişmez; istemcinin uydurduğu varyant yazılmaz. */
final class DeneylerTest extends WebTestCase
{
    private Connection $db;
    private KernelBrowser $istemci;

    protected function setUp(): void
    {
        $this->istemci = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $db->executeStatement('TRUNCATE deneyler, huni_ziyaretleri, ziyaret_deneyleri CASCADE');
    }

    public function testYonlendirmeAyniTohumdaAyniVaryantiSecer(): void
    {
        $this->ekle('acilis', 'yonlendirme', 'kontrol', [
            ['ad' => 'kontrol', 'pay' => 50, 'huni' => 'kontrol'],
            ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni'],
        ]);
        $deneyler = static::getContainer()->get(Deneyler::class);
        self::assertInstanceOf(Deneyler::class, $deneyler);

        $ilk = $deneyler->yonlendir('keepchat', 'kontrol', '123e4567-e89b-12d3-a456-426614174000');
        self::assertSame($ilk, $deneyler->yonlendir('keepchat', 'kontrol', '123e4567-e89b-12d3-a456-426614174000'));
        self::assertContains($ilk['huni'], ['kontrol', 'yeni']);
        self::assertArrayHasKey('acilis', $ilk['atama']);
    }

    public function testIcerikAtamasiYazilirUydurmaVaryantYazilmaz(): void
    {
        $this->ekle('baslik', 'icerik', 'genel', [
            ['ad' => 'a', 'pay' => 50],
            ['ad' => 'b', 'pay' => 50],
        ]);
        $this->istemci->jsonRequest('POST', '/huni/ziyaretler', [
            'app' => 'keepchat', 'huni' => 'genel', 'surum' => 1,
            'deneyler' => ['baslik' => 'b', 'sahte' => 'x'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $yanit = json_decode((string) $this->istemci->getResponse()->getContent(), true);
        self::assertIsArray($yanit);

        $ozellikler = static::getContainer()->get(Ozellikler::class);
        self::assertInstanceOf(Ozellikler::class, $ozellikler);
        self::assertSame('b', $ozellikler->ziyaretin((string) $yanit['ziyaret'])['deney.baslik']);
        self::assertFalse(isset($ozellikler->ziyaretin((string) $yanit['ziyaret'])['deney.sahte']));
    }

    /** @param list<array{ad: string, pay: int, huni?: string}> $varyantlar */
    private function ekle(string $ad, string $tur, string $huni, array $varyantlar): void
    {
        $this->db->insert('deneyler', [
            'app' => 'keepchat', 'ad' => $ad, 'tur' => $tur, 'huni' => $huni,
            'varyantlar' => json_encode($varyantlar, \JSON_THROW_ON_ERROR), 'durum' => 'aktif',
        ]);
    }
}
