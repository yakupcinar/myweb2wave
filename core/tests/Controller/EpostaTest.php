<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * E1: huninin e-posta adımı — adres ziyarete yazılır (serbest yanıtlardan
 * ayrı), pazarlama izni yalnız işaretlenince, ödeme sayfası adresle açılır,
 * izinsiz adres 90 gün sonra silinir.
 */
final class EpostaTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, reklam_donusumleri');
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testAdresKucukHarfleKaydedilirIzinIsaretsizseYok(): void
    {
        $z = $this->ziyaret('keepchat');

        $this->gonder($z, ['eposta' => '  Ayse.Yilmaz@Ornek.COM ']);

        self::assertResponseStatusCodeSame(204);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/eposta', $this->client->getResponse());
        self::assertSame(
            [['eposta' => 'ayse.yilmaz@ornek.com', 'izin' => false]],
            $this->db->fetchAllAssociative('SELECT eposta, pazarlama_izni_at IS NOT NULL AS izin FROM huni_epostalari'),
        );
        // Serbest yanıt tablosuna (rapor, AI) adres düşmez.
        self::assertSame(0, $this->say('huni_metin_cevaplari'));
    }

    public function testIzinIlkAnindaKalirKutuBosalincaKalkarSonAdresGecerli(): void
    {
        $z = $this->ziyaret('keepchat');
        $this->gonder($z, ['eposta' => 'a@ornek.com', 'pazarlamaIzni' => true]);
        $this->db->executeStatement("UPDATE huni_epostalari SET pazarlama_izni_at = '2026-01-01Z'");

        $this->gonder($z, ['eposta' => 'b@ornek.com', 'pazarlamaIzni' => true]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['eposta' => 'b@ornek.com', 'izin' => '2026-01-01 00:00:00+00'],
            $this->db->fetchAssociative("SELECT eposta, to_char(pazarlama_izni_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS+00') AS izin FROM huni_epostalari"),
        );

        $this->gonder($z, ['eposta' => 'b@ornek.com', 'pazarlamaIzni' => false]);
        self::assertSame(1, $this->say('huni_epostalari'));
        self::assertSame(0, $this->say('huni_epostalari WHERE pazarlama_izni_at IS NOT NULL'));
    }

    public function testGecersizGovde422VeKayitYok(): void
    {
        $z = $this->ziyaret('keepchat');
        foreach ([
            [],
            ['eposta' => 'adres-degil'],
            ['eposta' => 'a@'],
            ['eposta' => 42],
            ['eposta' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 62)],
            ['eposta' => 'a@ornek.com', 'pazarlamaIzni' => 'evet'],
            ['eposta' => 'a@ornek.com', 'ad' => 'Ayşe'],
        ] as $govde) {
            $this->gonder($z, $govde);
            self::assertResponseStatusCodeSame(422, (string) json_encode($govde));
        }
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/eposta', $this->client->getResponse());
        self::assertSame(0, $this->say('huni_epostalari'));

        // Sınırda: 254 karakter kabul.
        $this->gonder($z, ['eposta' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 61)]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testBilinmeyenZiyaret404(): void
    {
        foreach (['bozuk', '00000000-0000-4000-8000-000000000000'] as $z) {
            $this->gonder($z, ['eposta' => 'a@ornek.com']);
            self::assertResponseStatusCodeSame(404, $z);
        }
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/eposta', $this->client->getResponse());
        self::assertSame(0, $this->say('huni_epostalari'));
    }

    public function testOdemeSayfasiAdresleAcilir(): void
    {
        $z = $this->ziyaret('keepchat');
        $this->gonder($z, ['eposta' => 'ayse@ornek.com']);

        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $z]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('ayse@ornek.com', $this->oturumIstegi()['customer_email'] ?? null);
    }

    public function testAdressizYaDaBaskaUygulamaninZiyaretiAdresGondermez(): void
    {
        $adressiz = $this->ziyaret('keepchat');
        $baska = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('baska') RETURNING id");
        self::assertIsString($baska);
        $this->db->insert('huni_epostalari', ['ziyaret_id' => $baska, 'eposta' => 'baska@ornek.com']);

        foreach ([$adressiz, $baska, 'bozuk'] as $z) {
            $this->stripe->istekler = [];
            $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $z]);
            self::assertResponseStatusCodeSame(201, $z);
            self::assertArrayNotHasKey('customer_email', $this->oturumIstegi(), $z);
        }
    }

    public function testAdresZiyaretsizOlamaz(): void
    {
        // Tuzak §4: uçtaki "ziyaret var mı" sorgusu kısıtı görünmez kılar.
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->db->insert('huni_epostalari', ['ziyaret_id' => '00000000-0000-4000-8000-000000000000', 'eposta' => 'a@ornek.com']);
    }

    public function testBuyukHarfliAdresVeritabanindaReddedilir(): void
    {
        // Ödeme eşleşmesi (lower(email)) küçük harfe güvenir; uç normalleştiriyor, kısıt bekçi.
        $z = $this->ziyaret('keepchat');
        $this->expectException(DriverException::class);
        $this->db->insert('huni_epostalari', ['ziyaret_id' => $z, 'eposta' => 'A@ornek.com']);
    }

    public function testIzinsizEskiAdresSilinirIzinliVeGuncelKalir(): void
    {
        $eskiIzinsiz = $this->eskiZiyaret('91 days');
        $eskiIzinli = $this->eskiZiyaret('91 days');
        $guncel = $this->eskiZiyaret('89 days');
        $this->db->insert('huni_epostalari', ['ziyaret_id' => $eskiIzinsiz, 'eposta' => 'izinsiz@ornek.com']);
        $this->db->executeStatement("INSERT INTO huni_epostalari (ziyaret_id, eposta, pazarlama_izni_at) VALUES (:z, 'izinli@ornek.com', now())", ['z' => $eskiIzinli]);
        $this->db->insert('huni_epostalari', ['ziyaret_id' => $guncel, 'eposta' => 'guncel@ornek.com']);

        $tester = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:huni-metin-cevaplari-sil'));
        self::assertSame(0, $tester->execute([]));

        self::assertSame(['guncel@ornek.com', 'izinli@ornek.com'], $this->db->fetchFirstColumn('SELECT eposta FROM huni_epostalari ORDER BY eposta'));
        self::assertStringContainsString('izinsiz e-posta: 1', $tester->getDisplay());
    }

    /** @param array<string, mixed> $govde */
    private function gonder(string $ziyaret, array $govde): void
    {
        $this->client->jsonRequest('POST', "/huni/ziyaretler/{$ziyaret}/eposta", $govde);
    }

    /** @return array<mixed> son Checkout oturumu isteğinin parametreleri */
    private function oturumIstegi(): array
    {
        $oturumlar = array_values(array_filter($this->stripe->istekler, static fn (array $i): bool => str_ends_with($i['url'], '/v1/checkout/sessions')));
        self::assertCount(1, $oturumlar);

        return $oturumlar[0]['params'];
    }

    private function ziyaret(string $app): string
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => $app]);
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        $id = \is_array($j) ? ($j['ziyaret'] ?? null) : null;
        self::assertIsString($id);

        return $id;
    }

    private function eskiZiyaret(string $once): string
    {
        $id = $this->db->fetchOne(
            "INSERT INTO huni_ziyaretleri (app, created_at) VALUES ('keepchat', now() - CAST(:once AS interval)) RETURNING id",
            ['once' => $once],
        );
        self::assertIsString($id);

        return $id;
    }

    private function say(string $tablo): int
    {
        $n = $this->db->fetchOne("SELECT count(*) FROM {$tablo}");

        return is_numeric($n) ? (int) $n : -1;
    }
}
