<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Deneyler;
use App\Huni\Huniler;
use App\Ozellik\Ozellikler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M4a (A17): deneyler. Kararlar: varyant sekme tohumu + deney adının
 * SHA-256 kovasından (0–99, payların birikimli dilimi) — aynı tohum aynı
 * varyant; tohumsuz ziyaretçi deney dışı; ?huni= doğrudan adresi yönlendirme
 * deneyini ezmez; ziyarete yalnız o hunideki aktif deneyin geçerli varyantı
 * yazılır; taslak dışında varyantlar kilitli; geçişler taslak → aktif ↔ durdu
 * → bitti; aktife geçerken huniler yayında; hunide tek aktif yönlendirme.
 *
 * Beklenen kovalar bu koddan değil Python'dan (tuzak §3):
 * int(sha256("<deney>|<tohum>").hexdigest()[:8], 16) % 100 —
 * acilis|tohum-0001 = 94, acilis|tohum-0004 = 33, baslik|tohum-0001 = 81,
 * baslik|tohum-0003 = 28.
 */
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
        $db->executeStatement('TRUNCATE odeme_faturalari, deneyler, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari CASCADE');
        foreach (['genel', 'yeni', 'taslakta'] as $ad) {
            self::assertSame([], $this->huniler()->yeniHuni('keepchat', $ad, null));
            self::assertSame([], $this->huniler()->taslakKaydet('keepchat', $ad, ['sayfaBasligi' => $ad, 'adimlar' => [
                ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'dugme' => 'Devam', 'bloklar' => []],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
            ]]));
            if ('taslakta' !== $ad) {
                self::assertSame(1, $this->huniler()->yayinla('keepchat', $ad));
            }
        }
        $db->executeStatement("UPDATE huniler SET varsayilan = (ad = 'genel')");
    }

    public function testKovaPaylarinBirikimliDilimi(): void
    {
        $v = static fn (int $kontrol): array => [['ad' => 'kontrol', 'pay' => $kontrol], ['ad' => 'yeni', 'pay' => 100 - $kontrol]];
        // Kova 33: pay 33 → [0,33) dışında → yeni; pay 34 → kontrol (sınır).
        self::assertSame('yeni', Deneyler::sec($v(33), 'acilis', 'tohum-0004')['ad']);
        self::assertSame('kontrol', Deneyler::sec($v(34), 'acilis', 'tohum-0004')['ad']);
        self::assertSame('yeni', Deneyler::sec($v(94), 'acilis', 'tohum-0001')['ad']);
        self::assertSame('kontrol', Deneyler::sec($v(95), 'acilis', 'tohum-0001')['ad']);
        // Deney adı kovaya girer: aynı tohum başka deneyde başka kova.
        self::assertSame('kontrol', Deneyler::sec($v(30), 'baslik', 'tohum-0003')['ad']);
        self::assertSame('yeni', Deneyler::sec($v(30), 'acilis', 'tohum-0004')['ad']);
    }

    public function testKovaDagilimiPaylaraUyar(): void
    {
        $sayi = ['a' => 0, 'b' => 0];
        for ($i = 0; $i < 2000; ++$i) {
            ++$sayi[Deneyler::sec([['ad' => 'a', 'pay' => 90], ['ad' => 'b', 'pay' => 10]], 'dagilim', "t-{$i}")['ad']];
        }
        // Beklenen 200; ±4 standart sapma (≈ 54).
        self::assertGreaterThan(146, $sayi['b']);
        self::assertLessThan(254, $sayi['b']);
    }

    public function testYonlendirmeTanimIstegindeVaryantHunisiniVerir(): void
    {
        $this->ekle('acilis', 'yonlendirme', 'genel', [
            ['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'],
            ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni'],
        ]);

        self::assertSame(['huni' => 'yeni', 'deneyler' => ['acilis' => 'yeni']], $this->tanim('?tohum=tohum-0001'));
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->istemci->getResponse());
        self::assertSame(['huni' => 'genel', 'deneyler' => ['acilis' => 'kontrol']], $this->tanim('?tohum=tohum-0004'));
        // Doğrudan test adresi deneye girmez; tohumsuz / biçimsiz tohum da.
        self::assertSame(['huni' => 'genel', 'deneyler' => null], $this->tanim('?huni=genel&tohum=tohum-0001'));
        self::assertSame(['huni' => 'genel', 'deneyler' => null], $this->tanim(''));
        self::assertSame(['huni' => 'genel', 'deneyler' => null], $this->tanim('?tohum=kisa'));
    }

    public function testDurmusDeneyAtanmazIcerikDeneyiHunideAtanir(): void
    {
        $this->ekle('acilis', 'yonlendirme', 'genel', [
            ['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'],
            ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni'],
        ], 'durdu');
        $this->ekle('baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]);

        self::assertSame(['huni' => 'genel', 'deneyler' => ['baslik' => 'b']], $this->tanim('?tohum=tohum-0001'));
        self::assertSame(['huni' => 'genel', 'deneyler' => ['baslik' => 'a']], $this->tanim('?tohum=tohum-0003'));
    }

    public function testZiyaretYalnizKendiHunisindekiAktifDeneyinGecerliVaryantiniYazar(): void
    {
        $this->ekle('baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]);
        $this->ekle('acilis', 'yonlendirme', 'genel', [
            ['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'],
            ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni'],
        ]);
        $this->ekle('eski', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]], 'bitti');

        $genel = $this->ziyaret('genel', ['baslik' => 'b', 'acilis' => 'kontrol', 'eski' => 'a', 'sahte' => 'x']);
        self::assertSame(['deney.acilis' => 'kontrol', 'deney.baslik' => 'b'], $this->deneyOzellikleri($genel));
        // Yönlendirmede ziyaretin hunisi varyantın hunisi olmalı; içerik deneyi başka hunide.
        $yeni = $this->ziyaret('yeni', ['baslik' => 'b', 'acilis' => 'kontrol']);
        self::assertSame([], $this->deneyOzellikleri($yeni));
        $yeni = $this->ziyaret('yeni', ['acilis' => 'yeni', 'baslik' => 'zzz']);
        self::assertSame(['deney.acilis' => 'yeni'], $this->deneyOzellikleri($yeni));
    }

    public function testZiyaretGovdesindeBicimsizDeneylerReddedilir(): void
    {
        $this->istemci->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'huni' => 'genel', 'surum' => 1, 'deneyler' => 'b']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testKaydetVaryantlariDenetler(): void
    {
        $d = $this->deneyler();
        $h = static fn (array $v, string $tur = 'icerik', string $huni = 'genel'): array => $d->kaydet('keepchat', 'x', $tur, $huni, $v);
        self::assertSame(['Varyantlar 2–4 öğeli liste olmalı.'], $h([['ad' => 'a', 'pay' => 100]]));
        self::assertSame(['Payların toplamı 100 olmalı (şu an 90).'], $h([['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 40]]));
        self::assertSame(['Varyant adları farklı olmalı.'], $h([['ad' => 'a', 'pay' => 50], ['ad' => 'a', 'pay' => 50]]));
        self::assertSame(['Varyant 2: pay 1–99 tam sayı (yüzde) olmalı.'], $h([['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 0]]));
        self::assertSame(['Varyant 1: alanlar tam olarak ad, pay olmalı.'], $h([['ad' => 'a', 'pay' => 50, 'huni' => 'yeni'], ['ad' => 'b', 'pay' => 50]]));
        self::assertSame(['Varyant 1: ilk varyant deneyin hunisi olmalı (kontrol grubu).'], $h([['ad' => 'a', 'pay' => 50, 'huni' => 'yeni'], ['ad' => 'b', 'pay' => 50, 'huni' => 'genel']], 'yonlendirme'));
        self::assertSame(['Yönlendirme varyantlarının hunileri farklı olmalı.'], $h([['ad' => 'a', 'pay' => 50, 'huni' => 'genel'], ['ad' => 'b', 'pay' => 50, 'huni' => 'genel']], 'yonlendirme'));
        self::assertSame(['Varyant 2: huni bu uygulamada yok.'], $h([['ad' => 'a', 'pay' => 50, 'huni' => 'genel'], ['ad' => 'b', 'pay' => 50, 'huni' => 'yok']], 'yonlendirme'));
        self::assertSame(['Huni bu uygulamada yok.'], $h([['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]], 'icerik', 'yok'));
        self::assertSame([], $h([['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]));
        self::assertSame('taslak', $this->db->fetchOne("SELECT durum FROM deneyler WHERE ad = 'x'"));
    }

    public function testTaslakDisindaVaryantlarKilitli(): void
    {
        $d = $this->deneyler();
        $v = [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]];
        self::assertSame([], $d->kaydet('keepchat', 'baslik', 'icerik', 'genel', $v));
        self::assertSame([], $d->kaydet('keepchat', 'baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 30], ['ad' => 'b', 'pay' => 70]]));
        self::assertSame([], $d->durum('keepchat', 'baslik', 'aktif'));
        self::assertSame(["'baslik' deneyi aktif — yalnız taslak düzenlenir (sonuçlar karışmasın)."], $d->kaydet('keepchat', 'baslik', 'icerik', 'genel', $v));
        self::assertSame(30, $d->liste('keepchat')[0]['varyantlar'][0]['pay']);
    }

    public function testDurumGecisleri(): void
    {
        $d = $this->deneyler();
        self::assertNull($d->durum('keepchat', 'yok', 'aktif'));
        self::assertSame([], $d->kaydet('keepchat', 'baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]));
        self::assertSame(['taslak → durdu geçişi yok (taslak → aktif ↔ durdu → bitti).'], $d->durum('keepchat', 'baslik', 'durdu'));
        self::assertSame([], $d->durum('keepchat', 'baslik', 'aktif'));
        self::assertSame(['aktif → taslak geçişi yok (taslak → aktif ↔ durdu → bitti).'], $d->durum('keepchat', 'baslik', 'taslak'));
        self::assertSame([], $d->durum('keepchat', 'baslik', 'durdu'));
        // Aynı saniyede sürdürmek farkı gizlerdi: başlangıç bir gün önceye.
        $this->db->executeStatement("UPDATE deneyler SET baslangic_at = baslangic_at - interval '1 day'");
        $baslangic = $d->liste('keepchat')[0]['baslangic'];
        self::assertIsString($baslangic);
        self::assertSame([], $d->durum('keepchat', 'baslik', 'aktif'));
        self::assertSame($baslangic, $d->liste('keepchat')[0]['baslangic'], 'sürdürmek başlangıcı değiştirmez');
        self::assertNull($d->liste('keepchat')[0]['bitis']);
        self::assertSame([], $d->durum('keepchat', 'baslik', 'bitti'));
        self::assertIsString($d->liste('keepchat')[0]['bitis']);
        self::assertSame(['bitti → aktif geçişi yok (taslak → aktif ↔ durdu → bitti).'], $d->durum('keepchat', 'baslik', 'aktif'));
    }

    public function testAktifeGecerkenHunilerYayindaOlmali(): void
    {
        $d = $this->deneyler();
        self::assertSame([], $d->kaydet('keepchat', 'acilis', 'yonlendirme', 'genel', [
            ['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'],
            ['ad' => 'taslakta', 'pay' => 50, 'huni' => 'taslakta'],
        ]));
        self::assertSame(["'taslakta' hunisi yayında değil — önce yayınla."], $d->durum('keepchat', 'acilis', 'aktif'));
        self::assertSame('taslak', $d->liste('keepchat')[0]['durum']);
    }

    public function testHunideTekAktifYonlendirmeDeneyi(): void
    {
        $d = $this->deneyler();
        $v = [['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'], ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni']];
        self::assertSame([], $d->kaydet('keepchat', 'bir', 'yonlendirme', 'genel', $v));
        self::assertSame([], $d->kaydet('keepchat', 'iki', 'yonlendirme', 'genel', $v));
        self::assertSame([], $d->durum('keepchat', 'bir', 'aktif'));
        self::assertSame(["'genel' hunisinde zaten aktif bir yönlendirme deneyi var."], $d->durum('keepchat', 'iki', 'aktif'));
        self::assertSame([], $d->durum('keepchat', 'bir', 'durdu'));
        self::assertSame([], $d->durum('keepchat', 'iki', 'aktif'));
    }

    /** Tuzak §4: uygulama kontrolü olmadan kısıt doğrudan. */
    public function testTekAktifYonlendirmeVeritabaniKisiti(): void
    {
        $v = [['ad' => 'kontrol', 'pay' => 50, 'huni' => 'genel'], ['ad' => 'yeni', 'pay' => 50, 'huni' => 'yeni']];
        $this->ekle('bir', 'yonlendirme', 'genel', $v);
        $this->ekle('icerik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->ekle('iki', 'yonlendirme', 'genel', $v);
    }

    public function testKosuldaDeneyOzelligiKullanilir(): void
    {
        self::assertSame([], $this->huniler()->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'dugme' => 'Devam', 'bloklar' => [],
                'yonlendirme' => [['kosullar' => [['ozellik' => 'deney.baslik', 'islem' => 'esit', 'deger' => 'b']], 'hedef' => 'paywall']]],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
    }

    public function testPanelUclari(): void
    {
        $this->istemci->request('GET', '/panel/api/uygulamalar/keepchat/deneyler');
        self::assertResponseRedirects('/panel/giris');
        $this->istemci->request('GET', '/panel/deneyler');
        self::assertResponseRedirects('/panel/giris');
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->istemci->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        // Doğrudan adres / yenileme de kabuğu açar (yalnız menüden değil).
        $this->istemci->request('GET', '/panel/deneyler');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->istemci->getCrawler()->filter('#panel-uygulama'));

        $govde = ['ad' => 'baslik', 'tur' => 'icerik', 'huni' => 'genel', 'varyantlar' => [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]];
        $this->istemci->jsonRequest('POST', '/panel/api/uygulamalar/keepchat/deneyler', $govde, ['HTTP_X-CSRF-Token' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
        $this->istemci->jsonRequest('PUT', '/panel/api/uygulamalar/keepchat/deneyler/baslik/durum', ['durum' => 'aktif'], ['HTTP_X-CSRF-Token' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->db->fetchFirstColumn('SELECT ad FROM deneyler'));

        $this->istemci->request('GET', '/panel/anahtarlar');
        $jeton = (string) $this->istemci->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
        $this->istemci->jsonRequest('POST', '/panel/api/uygulamalar/keepchat/deneyler', $govde, ['HTTP_X-CSRF-Token' => $jeton]);
        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/deneyler', $this->istemci->getResponse());
        $this->istemci->jsonRequest('PUT', '/panel/api/uygulamalar/keepchat/deneyler/baslik/durum', ['durum' => 'aktif'], ['HTTP_X-CSRF-Token' => $jeton]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/deneyler/{ad}/durum', $this->istemci->getResponse());
        $this->istemci->jsonRequest('PUT', '/panel/api/uygulamalar/keepchat/deneyler/yok/durum', ['durum' => 'aktif'], ['HTTP_X-CSRF-Token' => $jeton]);
        self::assertResponseStatusCodeSame(404);
        $this->istemci->request('GET', '/panel/api/uygulamalar/keepchat/deneyler');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/deneyler', $this->istemci->getResponse());
        self::assertSame('no-store, private', $this->istemci->getResponse()->headers->get('Cache-Control'));
        $this->istemci->request('GET', '/panel/api/uygulamalar/yok/deneyler');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * M4b: beklenenler bu koddan değil Python'dan (tuzak §3) — iki varyant
     * Evan Miller'ın kapalı toplamıyla (lgamma), üç varyant 2 milyon örnekli
     * Monte Carlo (std. hata ≈ 0,0003).
     */
    public function testKazanmaOlasiligiBagimsizHesaplaAyni(): void
    {
        $iki = static fn (int $sA, int $nA, int $sB, int $nB): float => Deneyler::kazanmaOlasiliklari([[$sA, $nA], [$sB, $nB]])[1];
        self::assertEqualsWithDelta(1 / 6, $iki(1, 1, 0, 1), 0.001);
        self::assertEqualsWithDelta(0.853284, $iki(10, 100, 15, 100), 0.001);
        self::assertEqualsWithDelta(0.960623, $iki(30, 1000, 45, 1000), 0.001);
        self::assertEqualsWithDelta(0.928017, $iki(200, 10000, 230, 10000), 0.001);
        // Az ziyaretli geniş dağılım ↔ çok ziyaretli dar (ızgara çözünürlüğü).
        self::assertEqualsWithDelta(0.164664, $iki(3, 20, 40, 400), 0.001);
        self::assertEqualsWithDelta(0.5, $iki(5, 10, 50000, 100000), 0.002);
        self::assertEqualsWithDelta(0.921074, $iki(100000, 1000000, 100600, 1000000), 0.002);
        // 10 milyon ziyaret: sapma ≈ 1e-4 — tam [0, 1] ızgarada adım 5e-5,
        // dağılım iki noktaya düşer; daraltılmış ızgara çözer.
        self::assertEqualsWithDelta(0.868144, $iki(1000000, 10000000, 1001500, 10000000), 0.002);

        $uc = Deneyler::kazanmaOlasiliklari([[10, 100], [15, 100], [12, 100]]);
        self::assertEqualsWithDelta(0.0970, $uc[0], 0.003);
        self::assertEqualsWithDelta(0.6644, $uc[1], 0.003);
        self::assertEqualsWithDelta(0.2386, $uc[2], 0.003);
        self::assertEqualsWithDelta(1.0, array_sum($uc), 1e-9);
        // Veri yokken eşit; eşit veride eşit.
        foreach (Deneyler::kazanmaOlasiliklari([[0, 0], [0, 0], [0, 0], [0, 0]]) as $p) {
            self::assertEqualsWithDelta(0.25, $p, 1e-6);
        }
        self::assertEqualsWithDelta(0.5, Deneyler::kazanmaOlasiliklari([[7, 50], [7, 50]])[0], 1e-6);
    }

    /**
     * M4b (A17): sayım ziyaret başına; dönüşüm = abone oldu (deneme dahil),
     * ödedi ayrı; başka uygulamanın ziyareti sayılmaz; ziyaretsiz varyant 0.
     */
    public function testSonuclarZiyaretBasinaSayar(): void
    {
        $this->ekle('baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 30], ['ad' => 'c', 'pay' => 20]]);
        $a1 = $this->ziyaret('genel', ['baslik' => 'a']);
        $a2 = $this->ziyaret('genel', ['baslik' => 'a']);
        $a3 = $this->ziyaret('genel', ['baslik' => 'a']);
        $b1 = $this->ziyaret('genel', ['baslik' => 'b']);
        // a1: iki oturum (biri terk, biri ödedi) → bir ziyaret, abone + ödedi.
        $this->oturum('cs_a1_terk', $a1, null);
        $this->oturum('cs_a1', $a1, 999);
        // a2: kartlı deneme — Stripe "paid" der ama tutar 0: abone, ödemedi.
        $this->oturum('cs_a2', $a2, 0);
        // a3: Stripe no_payment_required — abone oldu, "paid" değil, tutar 0.
        $this->oturum('cs_a3', $a3, 0, paid: false);
        // b1: ödemeye geçti, tamamlamadı.
        $this->oturum('cs_b1', $b1, null);
        // Başka uygulamanın aynı adlı deneyi sayılmaz.
        $this->db->executeStatement("INSERT INTO huni_ziyaretleri (id, app) VALUES ('00000000-0000-4000-8000-000000000001', 'baska')");
        $this->db->executeStatement("INSERT INTO ziyaret_deneyleri (ziyaret_id, deney, varyant) VALUES ('00000000-0000-4000-8000-000000000001', 'baslik', 'a')");

        // M5c: deneme sonrası tahsil edilen fatura → a2 artık ödedi (Rapor::ODEDI).
        $this->db->executeStatement("UPDATE odeme_oturumlari SET stripe_subscription_id = 'sub_a2' WHERE stripe_checkout_session_id = 'cs_a2'");
        $this->db->insert('odeme_faturalari', ['stripe_invoice_id' => 'in_a2', 'stripe_subscription_id' => 'sub_a2', 'app' => 'keepchat', 'tutar_kucuk' => 999, 'para_birimi' => 'eur', 'odendi_at' => '2026-11-16 10:00Z']);

        $sonuc = $this->deneyler()->sonuclar('keepchat', 'baslik');
        self::assertNotNull($sonuc);
        self::assertSame(['ad' => 'baslik', 'durum' => 'aktif'], array_intersect_key($sonuc, ['ad' => 1, 'durum' => 1]));
        self::assertSame([
            ['a', 3, 3, 3, 2],
            ['b', 1, 1, 0, 0],
            ['c', 0, 0, 0, 0],
        ], array_map(static fn (array $v): array => [$v['ad'], $v['ziyaret'], $v['odemeyeGecti'], $v['abone'], $v['odedi']], $sonuc['varyantlar']));
        self::assertEqualsWithDelta(1.0, array_sum(array_column($sonuc['varyantlar'], 'kazanmaOlasiligi')), 0.001);
        // Olasılık abone / ziyaret sayılarından (ödemeye geçen ya da ödeyenden değil).
        self::assertSame(
            array_map(static fn (float $p): float => round($p, 4), Deneyler::kazanmaOlasiliklari([[3, 3], [0, 1], [0, 0]])),
            array_column($sonuc['varyantlar'], 'kazanmaOlasiligi'),
        );
        self::assertNull($this->deneyler()->sonuclar('keepchat', 'yok'));
        self::assertNull($this->deneyler()->sonuclar('baska', 'baslik'));
    }

    public function testPanelSonuclarUcu(): void
    {
        $this->ekle('baslik', 'icerik', 'genel', [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]);
        $this->istemci->request('GET', '/panel/api/uygulamalar/keepchat/deneyler/baslik/sonuclar');
        self::assertResponseRedirects('/panel/giris');
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->istemci->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->istemci->request('GET', '/panel/api/uygulamalar/keepchat/deneyler/baslik/sonuclar');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/deneyler/{ad}/sonuclar', $this->istemci->getResponse());
        self::assertSame('no-store, private', $this->istemci->getResponse()->headers->get('Cache-Control'));
        $this->istemci->request('GET', '/panel/api/uygulamalar/keepchat/deneyler/yok/sonuclar');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Webhook'un yazdığı biçim: tamamlanan oturumda abonelik anı + tutar;
     * Stripe "paid" dediyse tamamlandi_at da. null tutar = tamamlanmadı.
     */
    private function oturum(string $id, string $ziyaret, ?int $tutar, bool $paid = true): void
    {
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => $id, 'ziyaret_id' => $ziyaret] + (null === $tutar ? [] : [
            'abonelik_at' => '2026-11-09 10:00Z', 'tutar_kucuk' => $tutar, 'para_birimi' => 'eur',
        ] + ($paid ? ['tamamlandi_at' => '2026-11-09 10:00Z'] : [])));
    }

    /** @return array{huni: mixed, deneyler: mixed} */
    private function tanim(string $sorgu): array
    {
        $this->istemci->request('GET', '/huni/keepchat'.$sorgu);
        self::assertResponseIsSuccessful();
        $j = json_decode((string) $this->istemci->getResponse()->getContent(), true);
        self::assertIsArray($j);

        return ['huni' => $j['huni'] ?? null, 'deneyler' => $j['deneyler'] ?? null];
    }

    /** @param array<string, string> $deneyler */
    private function ziyaret(string $huni, array $deneyler): string
    {
        $this->istemci->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'huni' => $huni, 'surum' => 1, 'deneyler' => $deneyler]);
        self::assertResponseStatusCodeSame(201);
        $j = json_decode((string) $this->istemci->getResponse()->getContent(), true);
        self::assertIsArray($j);
        self::assertIsString($j['ziyaret']);

        return $j['ziyaret'];
    }

    /** @return array<string, mixed> */
    private function deneyOzellikleri(string $ziyaret): array
    {
        $o = static::getContainer()->get(Ozellikler::class);
        self::assertInstanceOf(Ozellikler::class, $o);

        return array_filter($o->ziyaretin($ziyaret), static fn (string $ad): bool => str_starts_with($ad, 'deney.'), \ARRAY_FILTER_USE_KEY);
    }

    /** @param list<array{ad: string, pay: int, huni?: string}> $varyantlar */
    private function ekle(string $ad, string $tur, string $huni, array $varyantlar, string $durum = 'aktif'): void
    {
        $this->db->insert('deneyler', [
            'app' => 'keepchat', 'ad' => $ad, 'tur' => $tur, 'huni' => $huni,
            'varyantlar' => json_encode($varyantlar, \JSON_THROW_ON_ERROR), 'durum' => $durum,
        ]);
    }

    private function deneyler(): Deneyler
    {
        $d = static::getContainer()->get(Deneyler::class);
        self::assertInstanceOf(Deneyler::class, $d);

        return $d;
    }

    private function huniler(): Huniler
    {
        $h = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $h);

        return $h;
    }
}
