<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Reklam atfı (A6): huni ziyareti → ödeme oturumu. */
final class AtifTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huni_ziyaretleri, odeme_oturumlari');
        ApiRequestor::setHttpClient(new KayitliStripeIstemcisi());
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testZiyaretReklamAlanlariylaKaydedilir(): void
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', [
            'app' => 'keepchat', 'utm_source' => 'meta', 'utm_campaign' => 'ekim-1', 'fbclid' => 'IwAR0abc',
        ]);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler', $this->client->getResponse());
        self::assertSame([
            'id' => $this->yanit()['ziyaret'] ?? null, 'app' => 'keepchat',
            'utm_source' => 'meta', 'utm_medium' => null, 'utm_campaign' => 'ekim-1',
            'utm_content' => null, 'utm_term' => null, 'fbclid' => 'IwAR0abc', 'ttclid' => null,
        ], $this->db->fetchAssociative('SELECT id, app, utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid, ttclid FROM huni_ziyaretleri'));
    }

    public function testReklamsizZiyaretDeKaydedilirBosAlanNullOlur(): void
    {
        // Organik ziyaret de sayılır: dönüşüm oranının paydası.
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'utm_source' => '']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame([null], $this->db->fetchFirstColumn('SELECT utm_source FROM huni_ziyaretleri'));
    }

    public function testBilinmeyenUygulama422VeKayitYok(): void
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'olmayan', 'utm_source' => 'meta']);

        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler', $this->client->getResponse());
        self::assertSame(0, $this->ziyaretSayisi());
    }

    public function testSinirdakiUzunlukKabulUstuReddedilir(): void
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'fbclid' => str_repeat('a', 500)]);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'fbclid' => str_repeat('a', 501)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid_request'], $this->yanit());
        self::assertSame(1, $this->ziyaretSayisi());
    }

    public function testDizgeOlmayanAlan422(): void
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'utm_source' => ['dizi']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->ziyaretSayisi());
    }

    public function testCorsHuniKokenineIzinVerirYabanciyaVermez(): void
    {
        foreach (['https://huni.test' => 'https://huni.test', 'https://kotu.example' => null] as $koken => $beklenen) {
            $this->client->request('OPTIONS', '/huni/ziyaretler', [], [], [
                'HTTP_ORIGIN' => $koken,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ]);
            self::assertSame($beklenen, $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'), $koken);
        }
    }

    public function testZiyaretliOdemeOturumuZiyareteBaglanir(): void
    {
        $ziyaret = $this->ziyaretAc('keepchat');

        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $ziyaret]);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $this->client->getResponse());
        $satir = $this->db->fetchAssociative('SELECT stripe_checkout_session_id, ziyaret_id, tamamlandi_at FROM odeme_oturumlari');
        self::assertIsArray($satir);
        self::assertSame($ziyaret, $satir['ziyaret_id']);
        self::assertIsString($satir['stripe_checkout_session_id']);
        self::assertStringStartsWith('cs_', $satir['stripe_checkout_session_id']);
        self::assertNull($satir['tamamlandi_at']);
    }

    public function testBaskaUygulamaninZiyaretiBaglanmazOdemeYineAcilir(): void
    {
        $baska = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('baska') RETURNING id");

        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $baska]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(0, $this->oturumSayisi());
    }

    public function testTaninmayanZiyaretOdemeyiDurdurmaz(): void
    {
        // Bozuk biçim Postgres'in uuid dönüşümünde 500 olurdu; bilinmeyen kimlik sessizce atlanır.
        foreach (['bozuk', '00000000-0000-4000-8000-000000000000'] as $ziyaret) {
            $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $ziyaret]);
            self::assertResponseStatusCodeSame(201, $ziyaret);
        }
        self::assertSame(0, $this->oturumSayisi());
    }

    public function testOdemeOturumuZiyaretsizOlamaz(): void
    {
        // Tuzak §4: uygulamadaki "ziyaret var mı" sorgusu kısıtı görünmez kılar.
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_k', 'ziyaret_id' => '00000000-0000-4000-8000-000000000000',
        ]);
    }

    private function ziyaretAc(string $app): string
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => $app]);
        $id = $this->yanit()['ziyaret'] ?? null;
        self::assertIsString($id);

        return $id;
    }

    /** @return array<mixed> */
    private function yanit(): array
    {
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($j) ? $j : [];
    }

    private function ziyaretSayisi(): int
    {
        $n = $this->db->fetchOne('SELECT count(*) FROM huni_ziyaretleri');

        return is_numeric($n) ? (int) $n : -1;
    }

    private function oturumSayisi(): int
    {
        $n = $this->db->fetchOne('SELECT count(*) FROM odeme_oturumlari');

        return is_numeric($n) ? (int) $n : -1;
    }
}
