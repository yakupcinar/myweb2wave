<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
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
        $this->db->executeStatement('TRUNCATE revenuecat_gidenler, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_surumleri, huni_kurallari, huniler, subscriptions');
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

    public function testSayimlarReklamOrganikAyriVeDonusum(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $this->client->request('GET', '/panel');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['2', '2', '1', '50,0 %'], $this->kart('reklam'));
        self::assertSame(['1', '0', '0', '0,0 %'], $this->kart('organik'));
    }

    public function testGunAraligiEskiZiyaretiAyirir(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $this->client->request('GET', '/panel?gun=90');

        self::assertSame(['2', '0', '0', '0,0 %'], $this->kart('organik'));
    }

    public function testSatirdaAsamaKaynakVeMaskeliEposta(): void
    {
        $this->veriKur();
        $this->girisYapmis();

        $this->client->request('GET', '/panel');
        $html = (string) $this->client->getResponse()->getContent();

        $odeyen = $this->client->getCrawler()->filter('tbody tr')->first()->filter('td')->each(static fn ($td): string => trim($td->text()));
        self::assertSame(['keepchat', '—', 'reklam · meta', 'ekim-1', 'ödedi', 'active', 'a***@example.com'], \array_slice($odeyen, 1));
        self::assertStringNotContainsString('ayse@example.com', $html);
    }

    public function testZararliUtmMetniKacislanir(): void
    {
        $this->db->insert('huni_ziyaretleri', ['app' => 'keepchat', 'utm_source' => '<script>alert(1)</script>']);
        $this->girisYapmis();

        $this->client->request('GET', '/panel');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)', $html);
    }

    public function testAnketAdimlariUlasanVeOran(): void
    {
        $this->anketKur();
        $this->girisYapmis();

        $this->client->request('GET', '/panel');

        self::assertSame(
            [['—', '1', 'karsilama', '3', '100 %'], ['—', '2', 'kiminle', '2', '67 %'], ['—', '3', 'cihaz', '1', '33 %']],
            $this->tablo('adimlar'),
        );
        self::assertSame([['kiminle', 'aile', '2'], ['kiminle', 'is', '1']], $this->tablo('cevaplar'));
    }

    public function testAnketAraligiEskiZiyaretinAdiminiSaymaz(): void
    {
        $this->anketKur();
        $eski = $this->ziyaretEkle([], '40 days');
        $this->db->insert('huni_adimlari', ['ziyaret_id' => $eski, 'sira' => 0, 'adim' => 'karsilama']);
        $this->girisYapmis();

        $this->client->request('GET', '/panel');
        self::assertSame('3', $this->tablo('adimlar')[0][3]);

        $this->client->request('GET', '/panel?gun=90');
        self::assertSame('4', $this->tablo('adimlar')[0][3]);
    }

    public function testAnketYokkenBolumGorunmez(): void
    {
        $this->girisYapmis();

        $this->client->request('GET', '/panel');

        self::assertSelectorNotExists('[data-bolum=adimlar]');
    }

    /** Üç ziyaret: biri 3 adım, biri 2, biri 1; kiminle → aile, aile, is. */
    private function anketKur(): void
    {
        foreach ([3 => 'aile', 2 => 'aile', 1 => null] as $kac => $cevap) {
            $z = $this->ziyaretEkle([], '1 hour');
            foreach (\array_slice(['karsilama', 'kiminle', 'cihaz'], 0, $kac) as $sira => $adim) {
                $this->db->insert('huni_adimlari', ['ziyaret_id' => $z, 'sira' => $sira, 'adim' => $adim]);
            }
            $this->db->insert('huni_cevaplari', ['ziyaret_id' => $z, 'soru' => 'kiminle', 'secenek' => $cevap ?? 'is']);
        }
    }

    /** @return list<list<string>> */
    private function tablo(string $bolum): array
    {
        return $this->client->getCrawler()->filter("[data-bolum={$bolum}] tbody tr")
            ->each(static fn ($tr): array => $tr->filter('td')->each(static fn ($td): string => trim($td->text())));
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

    /** @return list<string> sayfayı gördü, ödemeye geçti, ödedi, dönüşüm */
    private function kart(string $kaynak): array
    {
        return $this->client->getCrawler()->filter(".kart[data-kaynak={$kaynak}] dd")->each(static fn ($dd): string => trim($dd->text()));
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
