<?php

declare(strict_types=1);

namespace App\Tests\Ai;

use App\Ai\AiAyarlari;
use App\Huni\Huniler;
use App\Tests\Support\SahteClaude;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * A6: AI değerlendirmesi — brief başına geçici huni, gerçek asistan döngüsü
 * (Claude yerine SahteClaude), geçerli tanım oranı, tur, maliyet. Beklenen
 * maliyet elle: tur başına 1000 girdi × 4 + 200 çıktı × 20 = 8.000 mikro$.
 */
final class AiDegerlendirCommandTest extends KernelTestCase
{
    private Connection $db;
    private Huniler $huniler;
    private string $dosya;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ai_istekleri, uygulama_baglamlari CASCADE');
        $huniler = self::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        SahteClaude::sifirla();
        $this->dosya = sys_get_temp_dir().'/briefler-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($this->dosya, json_encode([
            ['ad' => 'kaydeden', 'istem' => 'Sayfa başlığını Deneme yap'],
            ['ad' => 'konusan', 'istem' => 'Ne yapabilirsin?'],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        @unlink($this->dosya);
        parent::tearDown();
    }

    public function testBriefBasinaGecerlilikTurMaliyetVeGeciciHuniSilinir(): void
    {
        $imza = $this->bosImza();
        // Brief 1: geçerli tanımı kaydeder, sonra özet (2 tur). Brief 2: yalnız konuşur (1 tur).
        $tanim = ['sayfaBasligi' => 'Deneme', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
        ]];
        SahteClaude::sirala([SahteClaude::arac('t1', 'taslak_kaydet', ['huni' => 'olc-1', 'imza' => $imza, 'tanim' => $tanim])], 'tool_use');
        SahteClaude::sirala([SahteClaude::metin('Başlığı değiştirdim.')]);
        SahteClaude::sirala([SahteClaude::metin('Huni kurabilirim.')]);

        [$kod, $cikti] = $this->calistir(['--efor' => 'high']);

        self::assertSame(Command::SUCCESS, $kod);
        self::assertSame(['olc-1', 'olc-2'], array_column($cikti['briefler'], 'huni'));
        self::assertSame(
            [['kaydeden', 'tamam', true, true, 2, 16000], ['konusan', 'tamam', false, false, 1, 8000]],
            array_map(static fn (array $b): array => [$b['ad'], $b['sonuc'], $b['kaydedildi'], $b['gecerli'], $b['tur'], $b['maliyetMikrodolar']], $cikti['briefler']),
        );
        self::assertSame(['brief' => 2, 'gecerli' => 1, 'gecerliOran' => 0.5, 'ortTur' => 1.5, 'toplamMaliyetMikrodolar' => 24000, 'model' => 'claude-opus-5-5', 'efor' => 'high'], array_diff_key($cikti['ozet'], ['ortSureMs' => 0]));
        // Efor istek gövdesine gider; ilk istemler brieflerinki.
        $ayar = SahteClaude::$istekler[0]['output_config'] ?? null;
        self::assertIsArray($ayar);
        self::assertSame('high', $ayar['effort'] ?? null);
        self::assertStringContainsString('Sayfa başlığını Deneme yap', json_encode(SahteClaude::$istekler[0]['messages'] ?? [], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        // Geçici huniler kaldırıldı, maliyet kayıtları kaldı.
        self::assertSame(['genel'], array_column($this->huniler->huniler('keepchat'), 'ad'));
        self::assertSame(['olc-1', 'olc-2'], $this->db->fetchFirstColumn('SELECT huni FROM ai_istekleri ORDER BY huni'));
    }

    /** Geçersiz tanım çekirdekte reddedilir (kaydedilmez) → brief geçersiz sayılır. */
    public function testReddedilenKayitGecersiz(): void
    {
        SahteClaude::sirala([SahteClaude::arac('t1', 'taslak_kaydet', ['huni' => 'olc-1', 'imza' => $this->bosImza(), 'tanim' => ['sayfaBasligi' => 'X', 'adimlar' => []]])], 'tool_use');
        SahteClaude::sirala([SahteClaude::metin('Olmadı.')]);
        SahteClaude::sirala([SahteClaude::metin('Tamam.')]);

        [, $cikti] = $this->calistir();

        self::assertSame([false, false], array_column($cikti['briefler'], 'gecerli'));
        self::assertSame(0.0, $cikti['ozet']['gecerliOran']);
    }

    public function testAnahtarYoksaIstekGitmezHuniAcilmaz(): void
    {
        self::getContainer()->set(AiAyarlari::class, new AiAyarlari('', 'claude-opus-5-5', 'medium', 8, 16000));

        $komut = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:ai-degerlendir'));
        self::assertSame(Command::FAILURE, $komut->execute(['--briefler' => $this->dosya, '--onek' => 'olc']));

        self::assertStringContainsString('ANTHROPIC_API_KEY', $komut->getDisplay());
        self::assertSame([], SahteClaude::$istekler);
        self::assertSame(['genel'], array_column($this->huniler->huniler('keepchat'), 'ad'));
    }

    public function testVarsayilanBriefSetiGecerliBicimde(): void
    {
        $set = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/config/ai/degerlendirme.json'), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($set);
        self::assertGreaterThanOrEqual(5, \count($set));
        foreach ($set as $b) {
            self::assertIsArray($b);
            self::assertSame(['ad', 'istem'], array_keys($b));
            self::assertIsString($b['ad']);
            self::assertMatchesRegularExpression('/^[a-z0-9-]{1,30}$/', $b['ad']);
        }
    }

    /** Boş şablonla açılan her hunin imzası aynı (imza = taslağın SHA-256'sı). */
    private function bosImza(): string
    {
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'imza-olcu', null));
        $imza = $this->huniler->taslakImzasi('keepchat', 'imza-olcu');
        self::assertIsString($imza);
        self::assertSame('silindi', $this->huniler->kaldir('keepchat', 'imza-olcu'));

        return $imza;
    }

    /**
     * @param array<string, string> $ek
     *
     * @return array{int, array{briefler: list<array<string, mixed>>, ozet: array<string, mixed>}}
     */
    private function calistir(array $ek = []): array
    {
        $komut = new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:ai-degerlendir'));
        $kod = $komut->execute(['--briefler' => $this->dosya, '--onek' => 'olc'] + $ek);
        /** @var array{briefler: list<array<string, mixed>>, ozet: array<string, mixed>} $cikti */
        $cikti = json_decode($komut->getDisplay(), true, 16, \JSON_THROW_ON_ERROR);

        return [$kod, $cikti];
    }
}
