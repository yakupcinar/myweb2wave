<?php

declare(strict_types=1);

namespace App\Tests\Olay;

use App\Atif\Ziyaretler;
use App\Huni\Huniler;
use App\Olay\Olaylar;
use Doctrine\DBAL\Connection;
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

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, subscriptions CASCADE');
        $ziyaretler = self::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $ziyaretler);
        $this->ziyaretler = $ziyaretler;
        $this->olaylar = new Olaylar($db);

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

    /** Ziyaret → izin → iki adım + cevaplar → ödeme → abonelik; zamanlar sabit. */
    private function tamZiyaret(string $app = 'keepchat', string $gun = '2026-11-01'): string
    {
        $z = $this->ziyaretler->kaydet($app, array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, true);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['aile', 'is']));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'Gizli metin'));
        self::assertTrue($this->ziyaretler->ilerle($z, 2, 'paywall', null));
        self::assertTrue($this->ziyaretler->oturumBagla($z, $app, 'cs_'.$z, null, null));
        $this->db->executeStatement("UPDATE odeme_oturumlari SET tamamlandi_at = '{$gun} 10:06Z', stripe_subscription_id = 'sub_{$z}', created_at = '{$gun} 10:05Z' WHERE ziyaret_id = :z", ['z' => $z]);
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app, updated_at) VALUES ('sub_{$z}', 'cus_1', 'active', 'a@example.com', :app, '{$gun} 10:07Z')", ['app' => $app]);
        $this->db->executeStatement("UPDATE huni_ziyaretleri SET created_at = '{$gun} 10:00Z', izin_at = '{$gun} 10:01Z' WHERE id = :z", ['z' => $z]);
        foreach (['kiminle' => '10:02', 'not' => '10:03', 'paywall' => '10:04'] as $adim => $saat) {
            $this->db->executeStatement("UPDATE huni_adimlari SET created_at = '{$gun} {$saat}Z' WHERE ziyaret_id = :z AND adim = :a", ['z' => $z, 'a' => $adim]);
        }
        $this->db->executeStatement("UPDATE huni_cevaplari SET updated_at = '{$gun} 10:02:30Z' WHERE ziyaret_id = :z", ['z' => $z]);
        $this->db->executeStatement("UPDATE huni_metin_cevaplari SET updated_at = '{$gun} 10:03:30Z' WHERE ziyaret_id = :z", ['z' => $z]);

        return $z;
    }

    public function testZiyaretinZamanCizelgesi(): void
    {
        $z = $this->tamZiyaret();

        self::assertSame([
            ['huni.ziyaret', ['huni' => 'genel', 'surum' => 1], '2026-11-01T10:00:00+00:00'],
            ['izin.verildi', [], '2026-11-01T10:01:00+00:00'],
            ['adim.goruldu', ['adim' => 'kiminle', 'sira' => 0], '2026-11-01T10:02:00+00:00'],
            ['cevap.verildi', ['soru' => 'kiminle', 'secenekler' => ['aile', 'is']], '2026-11-01T10:02:30+00:00'],
            ['adim.goruldu', ['adim' => 'not', 'sira' => 1], '2026-11-01T10:03:00+00:00'],
            // Serbest yanıt metni olaya girmez (Ç90) — yalnız hangi soru.
            ['cevap.verildi', ['soru' => 'not'], '2026-11-01T10:03:30+00:00'],
            ['adim.goruldu', ['adim' => 'paywall', 'sira' => 2], '2026-11-01T10:04:00+00:00'],
            ['odeme.baslatildi', [], '2026-11-01T10:05:00+00:00'],
            ['odeme.tamamlandi', [], '2026-11-01T10:06:00+00:00'],
            ['abonelik.durum', ['durum' => 'active'], '2026-11-01T10:07:00+00:00'],
        ], array_map(static fn (array $o): array => [$o['ad'], $o['veri'], $o['zaman']], $this->olaylar->ziyaretin($z)));
    }

    public function testIzniReddedenZiyaret(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, false);

        self::assertSame(['huni.ziyaret', 'izin.reddedildi'], array_column($this->olaylar->ziyaretin($z), 'ad'));
    }

    /** Akış imleçle sayfalanır: her olay tam bir kez, sırayla; başka uygulama yok. */
    public function testAkisImlecleSayfalanir(): void
    {
        $a = $this->tamZiyaret('keepchat', '2026-11-01');
        $b = $this->tamZiyaret('keepchat', '2026-11-02');
        $this->tamZiyaret('baska', '2026-11-01');

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
        $z = $this->tamZiyaret();
        // İki cevap aynı anda: ad aynı, imleç yine de ikisini de vermeli.
        $this->db->executeStatement("UPDATE huni_metin_cevaplari SET updated_at = '2026-11-01 10:02:30Z' WHERE ziyaret_id = :z", ['z' => $z]);

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
