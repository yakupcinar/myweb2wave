<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Huniler;
use App\Huni\Sablonlar;
use App\Huni\Simge\Simgeler;
use App\Huni\Tanim;
use App\Huni\Tema;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * V6: niş şablonları. Karar: şablon yeni huninin YAYINLANABİLİR başlangıcı
 * (uygulamanın planıyla), uygulamaya bağlı hiçbir şey taşımaz (görsel, plan),
 * uydurma kanıt içermez (yorum, sayı) ve tanımı kopyalanır.
 */
final class SablonlarTest extends KernelTestCase
{
    private const DIZIN = '/app/config/huni/sablonlar';

    private Huniler $huniler;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, medya');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
    }

    public function testListeDosyaAdiSirasiylaAdVeAciklamayla(): void
    {
        $liste = $this->sablonlar()->liste();

        self::assertSame(['dil', 'finans', 'fitness', 'uyku'], array_column($liste, 'anahtar'));
        self::assertSame('Uyku', array_column($liste, 'ad', 'anahtar')['uyku']);
        foreach ($liste as $s) {
            self::assertNotSame('', $s['aciklama'], $s['anahtar']);
        }
    }

    /** Her şablon şablondan açılan huni olarak doğrudan yayınlanabilir — yayın denetimi (kontrast, koşullar, plan) dahil. */
    public function testHerSablonAcilirVeYayinlanir(): void
    {
        foreach ($this->sablonlar()->liste() as ['anahtar' => $anahtar]) {
            self::assertSame([], $this->huniler->yeniHuni('keepchat', "s-{$anahtar}", null, $anahtar), $anahtar);
            self::assertIsInt($this->huniler->yayinla('keepchat', "s-{$anahtar}"), $anahtar);
        }
    }

    /** Plan dosyada boş, açılışta uygulamanın ilk planı; tema hazır temanın değerleri (kopya). */
    public function testPlanUygulamadanTemaHazirTemadan(): void
    {
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'gece', null, 'uyku'));
        $taslak = $this->huniler->taslak('keepchat', 'gece');
        self::assertIsArray($taslak);

        $paywall = array_values(array_filter((array) $taslak['adimlar'], static fn (mixed $a): bool => \is_array($a) && 'paywall' === $a['tur']));
        self::assertSame(['monthly'], array_column($paywall, 'plan'));
        self::assertIsArray($taslak['tema']);
        // Bağımsız beklenti (§3): gece temasının ana rengi ve zemini elle.
        self::assertSame(['#b7a6ff', '#0e1330'], [$taslak['tema']['anaRenk'], $taslak['tema']['arkaPlan']]);
    }

    public function testBilinmeyenSablonVeKaynaklaBirlikteReddedilir(): void
    {
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));

        self::assertSame(["şablon 'olmayan' yok"], $this->huniler->yeniHuni('keepchat', 'a', null, 'olmayan'));
        self::assertSame(['kaynak huni ya da şablon — ikisi birden olmaz'], $this->huniler->yeniHuni('keepchat', 'b', 'genel', 'uyku'));
        self::assertNull($this->huniler->taslak('keepchat', 'a'));
        self::assertNull($this->huniler->taslak('keepchat', 'b'));
    }

    /**
     * Dosyanın kendisi: görsel yok (medya uygulamaya ait), tema yalnız hazır
     * temanın adıyla, plan boş, yorum ve istatistik yok (uydurma kanıt —
     * yayından önce değiştirilmesi unutulursa yanıltıcı reklam olur).
     */
    public function testDosyadaUygulamayaBagliYaDaUydurmaIcerikYok(): void
    {
        foreach (glob(self::DIZIN.'/*.json') ?: [] as $dosya) {
            $s = json_decode((string) file_get_contents($dosya), true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($s);
            self::assertSame(['ad', 'aciklama', 'hazirTema', 'tanim'], array_keys($s), $dosya);
            self::assertIsString($s['hazirTema']);
            self::assertArrayHasKey($s['hazirTema'], Tema::HAZIR_TEMALAR, $dosya);
            self::assertIsArray($s['tanim']);
            self::assertArrayNotHasKey('tema', $s['tanim'], $dosya);
            self::assertSame([], Tanim::gorseller($s['tanim']), $dosya);

            $json = (string) json_encode($s['tanim'], \JSON_THROW_ON_ERROR);
            self::assertDoesNotMatchRegularExpression('/"tur":"(yorum|istatistik|gorsel|onceSonra)"/', $json, $dosya);
            self::assertDoesNotMatchRegularExpression('/"plan":"[^"]/', $json, $dosya);
        }
    }

    /** Simge cihazdan bağımsız görünsün (V3a): her emoji Fluent dizininde. */
    public function testSimgelerFluentDizininde(): void
    {
        $dizin = json_decode((string) file_get_contents('/app/config/huni/fluent-simgeler.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($dizin);
        self::assertIsArray($dizin['simgeler']);
        $sayi = 0;
        foreach (glob(self::DIZIN.'/*.json') ?: [] as $dosya) {
            preg_match_all('/"simge": "([^"]+)"/', (string) file_get_contents($dosya), $m);
            foreach ($m[1] as $emoji) {
                ++$sayi;
                $metin = json_decode('"'.$emoji.'"', true, 2, \JSON_THROW_ON_ERROR);
                self::assertIsString($metin);
                self::assertArrayHasKey(Simgeler::kod($metin), $dizin['simgeler'], "{$dosya}: {$emoji}");
            }
        }
        self::assertGreaterThan(40, $sayi);
    }

    private function sablonlar(): Sablonlar
    {
        $s = static::getContainer()->get(Sablonlar::class);
        self::assertInstanceOf(Sablonlar::class, $s);

        return $s;
    }
}
