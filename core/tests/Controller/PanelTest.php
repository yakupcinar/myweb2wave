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
        $this->db->executeStatement('TRUNCATE revenuecat_gidenler, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_surumleri, huni_kurallari, huniler, subscriptions');
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

        self::assertSame(['ziyaret' => 2, 'odemeye_gecti' => 2, 'odedi' => 1], $r['sayimlar']['reklam']);
        self::assertSame(['ziyaret' => 1, 'odemeye_gecti' => 0, 'odedi' => 0], $r['sayimlar']['organik']);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
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
     *               cevaplar: list<array{soru: string, secenek: string, sayi: int}>, metinCevaplari: list<array{cevap: string}>}
     */
    private function rapor(string $sorgu = ''): array
    {
        $this->client->request('GET', '/panel/api/rapor'.$sorgu);
        self::assertResponseIsSuccessful();
        $yanit = $this->client->getResponse();
        Sozlesme::yanitiDogrula('GET', '/panel/api/rapor', $yanit);
        /** @var array{gun: int, sayimlar: array{reklam: array<string, int>, organik: array<string, int>}, izinler: array<string, int>, satirlar: list<array<string, mixed>>, adimlar: list<array{adim: string, ulasan: int}>, cevaplar: list<array{soru: string, secenek: string, sayi: int}>, metinCevaplari: list<array{cevap: string}>} $govde */
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

        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_odeyen', 'ziyaret_id' => $odeyen, 'stripe_subscription_id' => 'sub_1', 'tamamlandi_at' => '2026-10-20 10:00:00+00']);
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_birakan', 'ziyaret_id' => $birakan]);
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_1', 'stripe_customer_id' => 'cus_1', 'status' => 'active', 'email' => 'ayse@example.com', 'app' => 'keepchat']);
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
