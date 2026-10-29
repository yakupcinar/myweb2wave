<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MetinCevaplariSilCommandTest extends KernelTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huni_ziyaretleri CASCADE');
    }

    public function testDoksanGunuGecenHamYanitiSilerGuncelYanitiVeAnalitigiTutar(): void
    {
        $eski = $this->ziyaret('91 days');
        $guncel = $this->ziyaret('89 days');
        foreach ([[$eski, 'eski'], [$guncel, 'yeni']] as [$ziyaret, $cevap]) {
            $this->db->insert('huni_metin_cevaplari', ['ziyaret_id' => $ziyaret, 'soru' => 'hedef', 'cevap' => $cevap]);
            $this->db->insert('huni_cevaplari', ['ziyaret_id' => $ziyaret, 'soru' => 'secim', 'secenek' => 'evet']);
        }
        $this->db->insert('huni_adimlari', ['ziyaret_id' => $eski, 'sira' => 0, 'adim' => 'soru']);

        $komut = (new Application(self::$kernel ?? self::bootKernel()))->find('app:huni-metin-cevaplari-sil');
        $tester = new CommandTester($komut);
        self::assertSame(0, $tester->execute([]));
        self::assertCount(1, $this->db->fetchFirstColumn('SELECT cevap FROM huni_metin_cevaplari'));
        self::assertSame(['yeni'], $this->db->fetchFirstColumn('SELECT cevap FROM huni_metin_cevaplari'));
        self::assertCount(2, $this->db->fetchFirstColumn('SELECT secenek FROM huni_cevaplari'));
        self::assertCount(1, $this->db->fetchFirstColumn('SELECT adim FROM huni_adimlari'));
        self::assertStringContainsString('90 günden eski ziyaret', $tester->getDisplay());
    }

    private function ziyaret(string $once): string
    {
        $id = $this->db->fetchOne(
            'INSERT INTO huni_ziyaretleri (app, created_at) VALUES (:app, now() - CAST(:once AS interval)) RETURNING id',
            ['app' => 'keepchat', 'once' => $once],
        );
        self::assertIsString($id);

        return $id;
    }
}
