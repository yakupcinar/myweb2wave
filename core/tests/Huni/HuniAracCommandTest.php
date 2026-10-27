<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Huniler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** A1: araç katmanı konsoldan — aynı araçlar, JSON girdi/çıktı, çıkış kodu. */
final class HuniAracCommandTest extends KernelTestCase
{
    private CommandTester $komut;
    private Huniler $huniler;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        $this->komut = new CommandTester((new Application($kernel))->find('app:huni-arac'));
    }

    public function testListeKapsamaGore(): void
    {
        self::assertSame(Command::SUCCESS, $this->komut->execute(['app' => 'keepchat', '--liste' => true]));
        self::assertContains('yeni_huni', array_column($this->cikti(), 'name'));

        $this->komut->execute(['app' => 'keepchat', '--liste' => true, '--huni' => 'genel']);
        self::assertNotContains('yeni_huni', array_column($this->cikti(), 'name'));
    }

    /** Depodan promptla huni: yeni → oku (imza) → kaydet; çıktı JSON, başarı 0. */
    public function testYeniOkuKaydet(): void
    {
        self::assertSame(Command::SUCCESS, $this->calistir('yeni_huni', ['huni' => 'fitness']));
        self::assertSame(Command::SUCCESS, $this->calistir('huni_oku', ['huni' => 'fitness']));
        $imza = $this->cikti()['imza'];
        $tanim = ['sayfaBasligi' => 'FitPlan', 'adimlar' => [
            ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Başlayalım.', 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Plan', 'metin' => 'Abone ol.', 'dugme' => 'Abone ol'],
        ]];

        self::assertSame(Command::SUCCESS, $this->calistir('taslak_kaydet', ['huni' => 'fitness', 'tanim' => $tanim, 'imza' => $imza]));
        self::assertTrue($this->cikti()['kaydedildi']);
        self::assertSame('FitPlan', $this->huniler->taslak('keepchat', 'fitness')['sayfaBasligi'] ?? null);
    }

    /** Araç hatası 1 + JSON hatalar; bozuk JSON ve bilinmeyen uygulama 2 (araç hiç çalışmaz). */
    public function testHataKodlari(): void
    {
        self::assertSame(Command::FAILURE, $this->calistir('huni_oku', ['huni' => 'yok']));
        self::assertSame(["'yok' adında huni yok"], $this->cikti()['hatalar']);

        self::assertSame(Command::INVALID, $this->komut->execute(['app' => 'keepchat', 'arac' => 'huni_oku', '--girdi' => '{bozuk']));
        self::assertSame(Command::INVALID, $this->komut->execute(['app' => 'baska', 'arac' => 'huniler_listele', '--girdi' => '{}']));
        self::assertSame(['bilinmeyen uygulama: baska'], $this->cikti()['hatalar']);
    }

    /** @param array<string, mixed> $girdi */
    private function calistir(string $arac, array $girdi): int
    {
        return $this->komut->execute(['app' => 'keepchat', 'arac' => $arac, '--girdi' => json_encode($girdi, \JSON_THROW_ON_ERROR)]);
    }

    /** @return array<array-key, mixed> */
    private function cikti(): array
    {
        $veri = json_decode($this->komut->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($veri);

        return $veri;
    }
}
