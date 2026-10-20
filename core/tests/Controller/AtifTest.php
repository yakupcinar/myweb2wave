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
        $this->db->executeStatement('TRUNCATE huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_surumleri, huni_kurallari, huniler');
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

    public function testIlerlemeAdimVeCevapKaydeder(): void
    {
        $z = $this->ziyaretAc('keepchat');

        $this->ilerle($z, ['sira' => 0, 'adim' => 'karsilama']);
        self::assertResponseStatusCodeSame(204);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/ilerleme', $this->client->getResponse());
        $this->ilerle($z, ['sira' => 1, 'adim' => 'kiminle', 'cevap' => 'aile']);

        self::assertSame(
            [['sira' => 0, 'adim' => 'karsilama'], ['sira' => 1, 'adim' => 'kiminle']],
            $this->db->fetchAllAssociative('SELECT sira, adim FROM huni_adimlari ORDER BY sira'),
        );
        self::assertSame([['soru' => 'kiminle', 'secenek' => 'aile']], $this->db->fetchAllAssociative('SELECT soru, secenek FROM huni_cevaplari'));
    }

    public function testGeriDonupYenidenGelmekBirSayilirSonCevapGecerli(): void
    {
        $z = $this->ziyaretAc('keepchat');

        $this->ilerle($z, ['sira' => 1, 'adim' => 'kiminle', 'cevap' => 'aile']);
        $this->ilerle($z, ['sira' => 1, 'adim' => 'kiminle', 'cevap' => 'is']);

        self::assertSame(1, $this->say('huni_adimlari'));
        self::assertSame(['is'], $this->db->fetchFirstColumn('SELECT secenek FROM huni_cevaplari'));
    }

    public function testBilinmeyenZiyaret404VeKayitYok(): void
    {
        foreach (['00000000-0000-4000-8000-000000000000', 'bozuk'] as $z) {
            $this->ilerle($z, ['sira' => 0, 'adim' => 'karsilama', 'cevap' => 'x']);
            self::assertResponseStatusCodeSame(404, $z);
        }
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/ilerleme', $this->client->getResponse());
        self::assertSame(0, $this->say('huni_adimlari') + $this->say('huni_cevaplari'));
    }

    public function testGecersizIlerleme422VeKayitYok(): void
    {
        $z = $this->ziyaretAc('keepchat');
        foreach ([
            ['sira' => -1, 'adim' => 'a'], ['sira' => 51, 'adim' => 'a'], ['sira' => '1', 'adim' => 'a'],
            ['sira' => 0, 'adim' => 'Büyük'], ['sira' => 0, 'adim' => str_repeat('a', 65)], ['sira' => 0],
            ['sira' => 0, 'adim' => 'a', 'cevap' => 5], ['sira' => 0, 'adim' => 'a', 'cevap' => 'b c'],
        ] as $govde) {
            $this->ilerle($z, $govde);
            self::assertResponseStatusCodeSame(422, (string) json_encode($govde));
        }
        $this->ilerle($z, ['sira' => 50, 'adim' => str_repeat('a', 64)]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->say('huni_adimlari'));
    }

    public function testIlerlemeUcundaCorsHuniKokenine(): void
    {
        $this->client->request('OPTIONS', '/huni/ziyaretler/00000000-0000-4000-8000-000000000000/ilerleme', [], [], [
            'HTTP_ORIGIN' => 'https://huni.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        self::assertSame('https://huni.test', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAdimKaydiZiyaretsizOlamaz(): void
    {
        // Tuzak §4: uçtaki "ziyaret var mı" sorgusu kısıtı görünmez kılar.
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->db->insert('huni_adimlari', ['ziyaret_id' => '00000000-0000-4000-8000-000000000000', 'sira' => 0, 'adim' => 'a']);
    }

    /** @param array<string, mixed> $govde */
    private function ilerle(string $ziyaret, array $govde): void
    {
        $this->client->jsonRequest('POST', "/huni/ziyaretler/{$ziyaret}/ilerleme", $govde);
    }

    private function say(string $tablo): int
    {
        $n = $this->db->fetchOne("SELECT count(*) FROM {$tablo}");

        return is_numeric($n) ? (int) $n : -1;
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
