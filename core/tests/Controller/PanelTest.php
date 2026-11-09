<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/** İç panel (A6/2): giriş ve "reklamdan mı geldi, ödedi mi". */
final class PanelTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Deneme sınırı kullanıcı adı + IP başına; testler birbirinin sayacını yemesin.
        $this->client->setServerParameter('REMOTE_ADDR', '10.9.'.random_int(0, 255).'.'.random_int(1, 254));
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE revenuecat_gidenler, odeme_faturalari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, huni_surumleri, huni_kurallari, huniler, subscriptions');
    }

    public function testGirisYapmadanPanelGorunmez(): void
    {
        $this->client->request('GET', '/panel');

        self::assertResponseRedirects('/panel/giris');
    }

    public function testDogruSifreyleGirilir(): void
    {
        $this->giris('yonetici', 'test-sifre-123');

        self::assertResponseRedirects('/panel');
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testYanlisSifreReddedilirHangiAlanOldugunuSoylemez(): void
    {
        foreach ([['yonetici', 'yanlis'], ['baskasi', 'test-sifre-123']] as [$ad, $sifre]) {
            $this->giris($ad, $sifre);
            self::assertResponseRedirects('/panel/giris');
            $this->client->followRedirect();
            self::assertSelectorTextSame('.hata', 'Kullanıcı adı ya da şifre yanlış.');
        }
        $this->client->request('GET', '/panel');
        self::assertResponseRedirects('/panel/giris');
    }

    public function testCsrfJetonuOlmadanGirilmez(): void
    {
        $this->client->request('POST', '/panel/giris', ['_username' => 'yonetici', '_password' => 'test-sifre-123']);
        $this->client->request('GET', '/panel');

        self::assertResponseRedirects('/panel/giris');
    }

    public function testBesYanlisDenemedenSonraDogruSifreDeReddedilir(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->giris('yonetici', 'yanlis-'.$i);
        }
        $this->giris('yonetici', 'test-sifre-123');
        $this->client->followRedirect();

        self::assertSelectorTextSame('.hata', 'Çok fazla deneme. Bir dakika sonra tekrar deneyin.');
        $this->client->request('GET', '/panel');
        self::assertResponseRedirects('/panel/giris');
    }

    public function testOzetYaDaAdBossaKimseGiremez(): void
    {
        foreach ([['yonetici', ''], ['', '$2y$04$x']] as [$ad, $ozet]) {
            try {
                (new YoneticiSaglayici($ad, $ozet))->loadUserByIdentifier($ad);
                self::fail("ad='{$ad}' özet='{$ozet}' ile kullanıcı yüklendi");
            } catch (UserNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testApiUclarinaOturumCereziVerilmez(): void
    {
        // Güvenlik duvarı yalnız /panel; API oturumsuz kalmalı.
        $this->client->request('GET', '/health');

        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testRaporUcuGirisYapmadanGirise(): void
    {
        // Panel uygulaması oturum düşmesini bu yönlendirmeden tanır (oturum.ts).
        $this->client->request('GET', '/panel/api/rapor');

        self::assertResponseRedirects('/panel/giris');
    }

    public function testSayimlarReklamOrganikAyri(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $r = $this->rapor();

        self::assertSame(['ziyaret' => 2, 'odemeye_gecti' => 2, 'deneme' => 0, 'odedi' => 1], $r['sayimlar']['reklam']);
        self::assertSame(['ziyaret' => 1, 'odemeye_gecti' => 0, 'deneme' => 0, 'odedi' => 0], $r['sayimlar']['organik']);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    /**
     * P4: günlük seri (UTC takvim günü) — aralığın her günü, boş gün 0; olay
     * deposundan (T4). Beklenen günler PHP saatinden, sorgudan bağımsız (§3).
     */
    public function testGunlukSeriHerGunSifirlaDolu(): void
    {
        // M5a: grafik olay deposundan — ziyaretEkle olaysız yazar, olaylar elle ve zamanlarıyla.
        foreach (['0 seconds', '1 day', '1 day', '3 days', '10 days'] as $once) {
            $this->olay($this->ziyaretEkle([], $once), 'huni.ziyaret', $once);
        }
        $dun = $this->ziyaretEkle([], '40 days');
        $this->olay($dun, 'odeme.baslatildi', '1 day');
        $this->olay($dun, 'odeme.tamamlandi', '0 seconds');
        $this->olay($dun, 'odeme.baslatildi', '0 seconds');
        $this->girisYapmis();

        $gun = static fn (int $once): string => gmdate('Y-m-d', time() - $once * 86400);
        $bos = static fn (int $once): array => ['gun' => $gun($once), 'ziyaret' => 0, 'odemeBaslatildi' => 0, 'odemeTamamlandi' => 0];
        self::assertSame([
            $bos(6), $bos(5), $bos(4),
            ['gun' => $gun(3), 'ziyaret' => 1, 'odemeBaslatildi' => 0, 'odemeTamamlandi' => 0],
            $bos(2),
            ['gun' => $gun(1), 'ziyaret' => 2, 'odemeBaslatildi' => 1, 'odemeTamamlandi' => 0],
            ['gun' => $gun(0), 'ziyaret' => 1, 'odemeBaslatildi' => 1, 'odemeTamamlandi' => 1],
        ], $this->rapor('?gun=7')['gunluk']);
        self::assertCount(30, $this->rapor()['gunluk']);
        self::assertSame(1, $this->rapor('?gun=30')['gunluk'][19]['ziyaret'], '10 gün önceki 30 günde');
    }

    /** Ç56 (3): izin oranı — yalnız sayı; seçmeyen = ne kabul ne ret. */
    public function testIzinSayilari(): void
    {
        foreach (['izin_at = now()', 'izin_at = now()', 'izin_red_at = now()', 'true'] as $i => $kosul) {
            $id = $this->ziyaretEkle([], ($i + 1).' minutes');
            if ('true' !== $kosul) {
                $this->db->executeStatement("UPDATE huni_ziyaretleri SET {$kosul} WHERE id = :id", ['id' => $id]);
            }
        }
        $this->girisYapmis();

        self::assertSame(['kabul' => 2, 'red' => 1, 'secmeyen' => 1], $this->rapor()['izinler']);
    }

    public function testGunAraligiEskiZiyaretiAyirirTanimsizAralik30(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $r = $this->rapor('?gun=90');
        self::assertSame(90, $r['gun']);
        self::assertSame(2, $r['sayimlar']['organik']['ziyaret']);
        self::assertSame(30, $this->rapor('?gun=5')['gun']);
    }

    /**
     * Ç118 (tuzak §52): kartlı denemede Stripe "paid" der, tutar 0 — "ödedi"
     * değil "deneme başladı". Ziyaret başına: denemesi de ödemesi de olan
     * ziyaret yalnız ödedi; abonelik anı olmayan "paid" oturum (eski kayıt)
     * ödedi sayılmaz. deneme + ödedi = abone olan ziyaret (deneylerle aynı).
     */
    public function testDenemeOdediSayilmazAyriSayilir(): void
    {
        $deneme = $this->ziyaretEkle(['fbclid' => 'fb-d'], '1 minute');
        $ikisi = $this->ziyaretEkle(['fbclid' => 'fb-i'], '2 minutes');
        $etiket = $this->ziyaretEkle(['fbclid' => 'fb-e'], '3 minutes');
        $oturum = function (string $id, string $ziyaret, ?int $tutar, string $sub): void {
            $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => $id, 'ziyaret_id' => $ziyaret, 'stripe_subscription_id' => $sub,
                'tamamlandi_at' => '2026-11-09 10:00:00+00', 'abonelik_at' => null === $tutar ? null : '2026-11-09 10:00:00+00', 'tutar_kucuk' => $tutar, 'para_birimi' => null === $tutar ? null : 'eur']);
        };
        $oturum('cs_deneme', $deneme, 0, 'sub_d');
        $oturum('cs_ikisi_deneme', $ikisi, 0, 'sub_i1');
        $oturum('cs_ikisi_odedi', $ikisi, 999, 'sub_i2');
        $oturum('cs_etiket', $etiket, null, 'sub_e');
        // Ödeme denemeden önce: "en yeni oturum" seçimi ödemeyi kaçırır.
        $this->db->executeStatement("UPDATE odeme_oturumlari SET abonelik_at = abonelik_at - interval '1 day' WHERE stripe_checkout_session_id = 'cs_ikisi_odedi'");
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_d', 'stripe_customer_id' => 'cus_d', 'status' => 'trialing', 'email' => 'deniz@example.com', 'app' => 'keepchat']);
        $this->girisYapmis();

        $r = $this->rapor();

        self::assertSame(['ziyaret' => 3, 'odemeye_gecti' => 3, 'deneme' => 1, 'odedi' => 1], $r['sayimlar']['reklam']);
        self::assertSame(
            [['deneme başladı', 'trialing', 'd***@example.com'], ['ödedi', null, null], ['ödemeye geçti', null, null]],
            array_map(static fn (array $s): array => [$s['asama'], $s['durum'], $s['email']], $r['satirlar']),
        );
    }

    /**
     * M5c: UTM kırılımı, cevaba göre dönüşüm, kohort. Beklenenler elle
     * hesaplandı (tuzak §3). A: deneme (tutar 0) + iki fatura (3. ve 35. gün)
     * → ödedi; B: ilk ödeme + fatura (1. saat); C: yalnız ödemeye geçti
     * (hepsi meta/cpc/ekim, 70 gün önce). D: deneme, fatura yok (organik);
     * E: google/cpc/kasim, TRY (2 gün önce). F: tiktok/cpc/ekim, TRY — yalnız
     * kaynağı farklı grup; G: organik, gelirli (ortam null grubu) — 70 gün.
     * H: meta/paid/ekim (yalnız ortamı farklı), faturası 3. gün; J: google,
     * ödemeye geçmedi — 2 gün. I: meta/cpc/ekim ama 100 gün (aralık dışı).
     */
    public function testUtmCevapVeKohort(): void
    {
        $a = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_medium' => 'cpc', 'utm_campaign' => 'ekim', 'fbclid' => 'fb-a'], '70 days');
        $b = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_medium' => 'cpc', 'utm_campaign' => 'ekim', 'fbclid' => 'fb-b'], '70 days');
        $c = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_medium' => 'cpc', 'utm_campaign' => 'ekim', 'fbclid' => 'fb-c'], '70 days');
        $d = $this->ziyaretEkle([], '2 days');
        $e = $this->ziyaretEkle(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'kasim'], '2 days');
        $f = $this->ziyaretEkle(['utm_source' => 'tiktok', 'utm_medium' => 'cpc', 'utm_campaign' => 'ekim'], '70 days');
        $g = $this->ziyaretEkle([], '70 days');
        $h = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_campaign' => 'ekim', 'fbclid' => 'fb-h'], '2 days');
        $this->ziyaretEkle(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'kasim'], '2 days');
        $i = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_medium' => 'cpc', 'utm_campaign' => 'ekim', 'fbclid' => 'fb-i'], '100 days');
        foreach ([[$a, 0, 'eur'], [$b, 999, 'eur'], [$c, null, null], [$d, 0, 'eur'], [$e, 2000, 'try'], [$f, 1500, 'try'], [$g, 450, 'eur'], [$h, 700, 'eur'], [$i, 999, 'eur']] as $n => [$z, $tutar, $para]) {
            $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => "cs_{$n}", 'ziyaret_id' => $z, 'stripe_subscription_id' => null === $tutar ? null : "sub_{$n}",
                'abonelik_at' => null === $tutar ? null : '2026-11-01 10:00:00+00', 'tutar_kucuk' => $tutar, 'para_birimi' => $para]);
        }
        foreach ([['in_a1', 'sub_0', 999, 'eur', $a, '3 days'], ['in_a2', 'sub_0', 999, 'eur', $a, '35 days'], ['in_b1', 'sub_1', 999, 'eur', $b, '1 hour'], ['in_e', 'sub_4', 2000, 'try', $e, '1 hour'],
            ['in_f', 'sub_5', 1500, 'try', $f, '1 hour'], ['in_g', 'sub_6', 450, 'eur', $g, '2 days'], ['in_h', 'sub_7', 700, 'eur', $h, '3 days'],
            ['in_i', 'sub_8', 999, 'eur', $i, '1 hour']] as [$id, $sub, $tutar, $para, $z, $sonra]) {
            $this->db->executeStatement(
                "INSERT INTO odeme_faturalari (stripe_invoice_id, stripe_subscription_id, app, tutar_kucuk, para_birimi, odendi_at)
                 SELECT :id, :sub, 'keepchat', :tutar, :para, created_at + CAST(:sonra AS interval) FROM huni_ziyaretleri WHERE id = :z",
                ['id' => $id, 'sub' => $sub, 'tutar' => $tutar, 'para' => $para, 'z' => $z, 'sonra' => $sonra],
            );
        }
        foreach ([[$a, 'aile'], [$b, 'aile'], [$c, 'is'], [$d, 'is']] as [$z, $secenek]) {
            $this->db->insert('huni_cevaplari', ['ziyaret_id' => $z, 'soru' => 'kiminle', 'secenek' => $secenek]);
        }
        $this->girisYapmis();

        $r = $this->rapor('?gun=90');

        self::assertSame([
            ['kaynak' => 'meta', 'ortam' => 'cpc', 'kampanya' => 'ekim', 'reklam' => true, 'ziyaret' => 3, 'odemeye_gecti' => 3, 'deneme' => 0, 'odedi' => 2, 'gelir' => [['paraBirimi' => 'EUR', 'tutar' => 29.97]]],
            ['kaynak' => 'doğrudan', 'ortam' => null, 'kampanya' => null, 'reklam' => false, 'ziyaret' => 2, 'odemeye_gecti' => 2, 'deneme' => 1, 'odedi' => 1, 'gelir' => [['paraBirimi' => 'EUR', 'tutar' => 4.5]]],
            ['kaynak' => 'google', 'ortam' => 'cpc', 'kampanya' => 'kasim', 'reklam' => true, 'ziyaret' => 2, 'odemeye_gecti' => 1, 'deneme' => 0, 'odedi' => 1, 'gelir' => [['paraBirimi' => 'TRY', 'tutar' => 20]]],
            ['kaynak' => 'meta', 'ortam' => 'paid', 'kampanya' => 'ekim', 'reklam' => true, 'ziyaret' => 1, 'odemeye_gecti' => 1, 'deneme' => 0, 'odedi' => 1, 'gelir' => [['paraBirimi' => 'EUR', 'tutar' => 7]]],
            ['kaynak' => 'tiktok', 'ortam' => 'cpc', 'kampanya' => 'ekim', 'reklam' => true, 'ziyaret' => 1, 'odemeye_gecti' => 1, 'deneme' => 0, 'odedi' => 1, 'gelir' => [['paraBirimi' => 'TRY', 'tutar' => 15]]],
        ], $r['utm']);
        // A deneme ile başladı ama faturası ödendi → "ödedi" (deneme değil).
        self::assertSame(['ziyaret' => 7, 'odemeye_gecti' => 6, 'deneme' => 0, 'odedi' => 5], $r['sayimlar']['reklam']);
        self::assertSame(['ziyaret' => 2, 'odemeye_gecti' => 2, 'deneme' => 1, 'odedi' => 1], $r['sayimlar']['organik']);
        self::assertSame([
            ['app' => 'keepchat', 'soru' => 'kiminle', 'secenek' => 'aile', 'sayi' => 2, 'abone' => 2],
            ['app' => 'keepchat', 'soru' => 'kiminle', 'secenek' => 'is', 'sayi' => 2, 'abone' => 1],
        ], $r['cevaplar']);

        // Hafta = UTC pazartesi, PHP saatinden (sorgudan bağımsız).
        $hafta = static function (int $once): string {
            $t = time() - $once * 86400;

            return gmdate('Y-m-d', $t - ((int) gmdate('N', $t) - 1) * 86400);
        };
        $p = static fn (int $gun, bool $olgun, array $kisiBasi = []): array => ['gun' => $gun, 'olgun' => $olgun, 'kisiBasi' => $kisiBasi];
        $eur = static fn (int|float $t): array => [['paraBirimi' => 'EUR', 'tutar' => $t]];
        $try = static fn (int|float $t): array => [['paraBirimi' => 'TRY', 'tutar' => $t]];
        self::assertSame([
            // 2 gün (D E H J): yalnız ilk 24 saat olgun; E'nin faturası 1. saatte, 20 TRY / 4 ziyaret; H'ninki 3. gün.
            ['hafta' => $hafta(2), 'ziyaret' => 4, 'pencereler' => [$p(0, true, $try(5)), $p(7, false), $p(30, false), $p(60, false)]],
            // 70 gün, 5 ziyaret (A B C F G). EUR: B 1. saat 9,99; A 3. gün +9,99; G 2. gün +4,50; A 35. gün +9,99
            // → 9,99 / 5 = 2,00 · 24,48 / 5 = 4,90 · 4,90 · 34,47 / 5 = 6,89. TRY: F 1. saat 15 / 5 = 3 her pencerede.
            ['hafta' => $hafta(70), 'ziyaret' => 5, 'pencereler' => [$p(0, true, [...$eur(2), ...$try(3)]), $p(7, true, [...$eur(4.9), ...$try(3)]),
                $p(30, true, [...$eur(4.9), ...$try(3)]), $p(60, true, [...$eur(6.89), ...$try(3)])]],
        ], $r['kohort']);
    }

    /**
     * M5c: pencere haftanın EN GENÇ ziyareti için dolmadıysa olgun değil.
     * Hafta, 30 gün öncesini içeren UTC haftası: ilk ziyaret pazartesi 00:00
     * (yaşı ≥ 30 gün), ikincisi "30 gün önce" ile hafta sonu arasında (yaşı <
     * 30 gün) — her gün koşulan test için ikisi de aynı haftada.
     */
    public function testKohortOlgunlukHaftaninEnGencZiyaretinden(): void
    {
        $simdi = time();
        $sinir = $simdi - 30 * 86400;
        $pazartesi = $sinir - ((int) gmdate('N', $sinir) - 1) * 86400 - $sinir % 86400;
        $genc = intdiv($sinir + $pazartesi + 7 * 86400, 2);
        foreach ([$pazartesi, $genc] as $t) {
            $this->ziyaretEkle([], ($simdi - $t).' seconds');
        }
        $this->girisYapmis();

        $kohort = $this->rapor('?gun=90')['kohort'];
        self::assertSame([[gmdate('Y-m-d', $pazartesi), 2]], array_map(static fn (array $k): array => [$k['hafta'], $k['ziyaret']], $kohort));
        self::assertSame([0 => true, 7 => true, 30 => false, 60 => false], array_column($kohort[0]['pencereler'], 'olgun', 'gun'));
    }

    public function testSatirdaAsamaKaynakVeMaskeliEposta(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $r = $this->rapor();

        $odeyen = $r['satirlar'][0];
        unset($odeyen['zaman']);
        self::assertSame(['app' => 'keepchat', 'huni' => null, 'kaynak' => 'meta', 'kampanya' => 'ekim-1', 'reklam' => true, 'asama' => 'ödedi', 'durum' => 'active', 'email' => 'a***@example.com'], $odeyen);
        self::assertStringNotContainsString('ayse@example.com', (string) $this->client->getResponse()->getContent());
    }

    public function testAnketAdimlariVeCevaplar(): void
    {
        $this->anketKur();
        $this->girisYapmis();

        $r = $this->rapor();

        self::assertSame([3, 2, 1], array_column($r['adimlar'], 'ulasan'));
        self::assertSame(['karsilama', 'kiminle', 'cihaz'], array_column($r['adimlar'], 'adim'));
        self::assertSame([['kiminle', 'aile', 2], ['kiminle', 'is', 2]], array_map(static fn (array $c): array => [$c['soru'], $c['secenek'], $c['sayi']], $r['cevaplar']));
    }

    public function testAnketAraligiEskiZiyaretinAdiminiSaymaz(): void
    {
        $this->anketKur();
        $eski = $this->ziyaretEkle([], '40 days');
        $this->db->insert('huni_adimlari', ['ziyaret_id' => $eski, 'sira' => 0, 'adim' => 'karsilama']);
        $this->girisYapmis();

        self::assertSame(3, $this->rapor()['adimlar'][0]['ulasan']);
        self::assertSame(4, $this->rapor('?gun=90')['adimlar'][0]['ulasan']);
    }

    public function testSerbestYanitYalnizOturumluPanelde(): void
    {
        $z = $this->ziyaretEkle([], '1 hour');
        $this->db->insert('huni_adimlari', ['ziyaret_id' => $z, 'sira' => 1, 'adim' => 'hedef']);
        $this->db->insert('huni_metin_cevaplari', ['ziyaret_id' => $z, 'soru' => 'hedef', 'cevap' => 'Daha iyi uyumak']);
        $this->client->request('GET', '/panel/api/rapor');
        self::assertResponseRedirects('/panel/giris');

        $this->girisYapmis();
        self::assertSame(['Daha iyi uyumak'], array_column($this->rapor()['metinCevaplari'], 'cevap'));
    }

    /** Üç ziyaret: biri çoklu aile+iş, biri aile, biri iş seçer. */
    private function anketKur(): void
    {
        foreach ([3 => 'aile', 2 => 'aile', 1 => null] as $kac => $cevap) {
            $z = $this->ziyaretEkle([], '1 hour');
            foreach (\array_slice(['karsilama', 'kiminle', 'cihaz'], 0, $kac) as $sira => $adim) {
                $this->db->insert('huni_adimlari', ['ziyaret_id' => $z, 'sira' => $sira, 'adim' => $adim]);
            }
            $this->db->insert('huni_cevaplari', ['ziyaret_id' => $z, 'soru' => 'kiminle', 'secenek' => $cevap ?? 'is']);
            if (3 === $kac) {
                $this->db->insert('huni_cevaplari', ['ziyaret_id' => $z, 'soru' => 'kiminle', 'secenek' => 'is']);
            }
        }
    }

    private function giris(string $ad, string $sifre): void
    {
        $crawler = $this->client->request('GET', '/panel/giris');
        $this->client->submit($crawler->selectButton('Giriş')->form(['_username' => $ad, '_password' => $sifre]));
    }

    private function girisYapmis(): void
    {
        // Sağlayıcının kullanıcısı: şifre özeti farklı bir kullanıcı oturum
        // yenilenirken "değişti" sayılıp düşürülür.
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    /**
     * Rapor ucu; yanıt sözleşmeye (PanelRaporu) göre doğrulanır — şekil
     * orada ölçüldü, tip açıklaması yalnız erişim için.
     *
     * @return array{gun: int, sayimlar: array{reklam: array<string, int>, organik: array<string, int>}, izinler: array<string, int>,
     *               satirlar: list<array<string, mixed>>, adimlar: list<array{adim: string, ulasan: int}>,
     *               cevaplar: list<array{soru: string, secenek: string, sayi: int, abone: int}>, metinCevaplari: list<array{cevap: string}>,
     *               gunluk: list<array{gun: string, ziyaret: int, odemeBaslatildi: int, odemeTamamlandi: int}>,
     *               utm: list<array<string, mixed>>, kohort: list<array{hafta: string, ziyaret: int, pencereler: list<array{gun: int, olgun: bool, kisiBasi: list<mixed>}>}>}
     */
    private function rapor(string $sorgu = ''): array
    {
        $this->client->request('GET', '/panel/api/rapor'.$sorgu);
        self::assertResponseIsSuccessful();
        $yanit = $this->client->getResponse();
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor', $yanit);
        /** @var array{gun: int, sayimlar: array{reklam: array<string, int>, organik: array<string, int>}, izinler: array<string, int>, satirlar: list<array<string, mixed>>, adimlar: list<array{adim: string, ulasan: int}>, cevaplar: list<array{soru: string, secenek: string, sayi: int, abone: int}>, metinCevaplari: list<array{cevap: string}>, gunluk: list<array{gun: string, ziyaret: int, odemeBaslatildi: int, odemeTamamlandi: int}>, utm: list<array<string, mixed>>, kohort: list<array{hafta: string, ziyaret: int, pencereler: list<array{gun: int, olgun: bool, kisiBasi: list<mixed>}>}>} $govde */
        $govde = json_decode((string) $yanit->getContent(), true);

        return $govde;
    }

    /**
     * Reklam: ödeyen (fbclid, en yeni) + ödemeye geçip bırakan (utm cpc).
     * Organik: yalnız gördü; 40 gün önceki organik yalnız 90 günde.
     */
    private function veriKur(): void
    {
        $odeyen = $this->ziyaretEkle(['utm_source' => 'meta', 'utm_campaign' => 'ekim-1', 'fbclid' => 'IwAR0x'], '1 minute');
        $birakan = $this->ziyaretEkle(['utm_source' => 'google', 'utm_medium' => 'cpc'], '2 hours');
        $this->ziyaretEkle([], '3 hours');
        $this->ziyaretEkle([], '40 days');

        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_odeyen', 'ziyaret_id' => $odeyen, 'stripe_subscription_id' => 'sub_1', 'tamamlandi_at' => '2026-10-20 10:00:00+00',
            'abonelik_at' => '2026-10-20 10:00:00+00', 'tutar_kucuk' => 999, 'para_birimi' => 'eur']);
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_birakan', 'ziyaret_id' => $birakan]);
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_1', 'stripe_customer_id' => 'cus_1', 'status' => 'active', 'email' => 'ayse@example.com', 'app' => 'keepchat']);
    }

    private function olay(string $ziyaret, string $ad, string $once): void
    {
        $this->db->executeStatement(
            "INSERT INTO olaylar (ziyaret_id, app, ad, zaman) VALUES (:z, 'keepchat', :ad, now() - CAST(:once AS interval))",
            ['z' => $ziyaret, 'ad' => $ad, 'once' => $once],
        );
    }

    /** @param array<string, string> $alanlar */
    private function ziyaretEkle(array $alanlar, string $once): string
    {
        $id = $this->db->fetchOne(
            'INSERT INTO huni_ziyaretleri (app, utm_source, utm_medium, utm_campaign, fbclid, created_at)
             VALUES (:app, :utm_source, :utm_medium, :utm_campaign, :fbclid, now() - CAST(:once AS interval)) RETURNING id',
            array_merge(['app' => 'keepchat', 'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'fbclid' => null, 'once' => $once], $alanlar),
        );
        self::assertIsString($id);

        return $id;
    }
}
