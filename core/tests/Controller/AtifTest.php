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
        $this->db->executeStatement('TRUNCATE reklam_donusumleri, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_surumleri, huni_kurallari, huniler');
        ApiRequestor::setHttpClient(new KayitliStripeIstemcisi());
        // Ç34: hız sınırı durumu testler arasında taşınmasın.
        $limiter = static::getContainer()->get('cache.rate_limiter');
        self::assertTrue(method_exists($limiter, 'clear') && $limiter->clear());
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testZiyaretReklamAlanlariylaKaydedilir(): void
    {
        // H6a: tıklama kimliği yalnız izinle — önceden izin vermiş ziyaretçi.
        $this->client->jsonRequest('POST', '/huni/ziyaretler', [
            'app' => 'keepchat', 'utm_source' => 'meta', 'utm_campaign' => 'ekim-1', 'fbclid' => 'IwAR0abc', 'izin' => true,
        ]);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler', $this->client->getResponse());
        self::assertSame([
            'id' => $this->yanit()['ziyaret'] ?? null, 'app' => 'keepchat',
            'utm_source' => 'meta', 'utm_medium' => null, 'utm_campaign' => 'ekim-1',
            'utm_content' => null, 'utm_term' => null, 'fbclid' => 'IwAR0abc', 'ttclid' => null,
        ], $this->db->fetchAssociative('SELECT id, app, utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid, ttclid FROM huni_ziyaretleri'));
        self::assertSame(1, $this->say('huni_ziyaretleri WHERE izin_at IS NOT NULL'));
    }

    /** H6a (A12): izin yoksa tıklama kimlikleri atılır — eski önbellekli betik gönderse bile; UTM kalır. */
    public function testIzinsizZiyaretTiklamaKimligiSaklamaz(): void
    {
        $this->client->jsonRequest('POST', '/huni/ziyaretler', [
            'app' => 'keepchat', 'utm_source' => 'meta', 'fbclid' => 'IwAR0abc', 'ttclid' => 'E.C.P.x',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(
            ['utm_source' => 'meta', 'fbclid' => null, 'ttclid' => null, 'izin_at' => null],
            $this->db->fetchAssociative('SELECT utm_source, fbclid, ttclid, izin_at FROM huni_ziyaretleri'),
        );

        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'izin' => 'evet']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testSonradanVerilenIzinKimlikleriYazarEskisiniSilmez(): void
    {
        $ziyaret = $this->ziyaretAc('keepchat');

        $this->client->jsonRequest('POST', "/huni/ziyaretler/{$ziyaret}/izin", ['fbclid' => 'IwAR0abc']);
        self::assertResponseStatusCodeSame(204);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/izin', $this->client->getResponse());
        $ilk = $this->db->fetchOne('SELECT izin_at FROM huni_ziyaretleri');
        self::assertIsString($ilk);

        // İkinci izin (ör. başka sekmede): ilk an ve fbclid korunur, ttclid eklenir.
        $this->client->jsonRequest('POST', "/huni/ziyaretler/{$ziyaret}/izin", ['ttclid' => 'E.C.P.x']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(
            ['fbclid' => 'IwAR0abc', 'ttclid' => 'E.C.P.x', 'izin_at' => $ilk],
            $this->db->fetchAssociative('SELECT fbclid, ttclid, izin_at FROM huni_ziyaretleri'),
        );
    }

    public function testIzinGecersizGovde422BilinmeyenZiyaret404(): void
    {
        $ziyaret = $this->ziyaretAc('keepchat');
        foreach ([['utm_source' => 'x'], ['fbclid' => ''], ['fbclid' => ['dizi']], ['fbclid' => str_repeat('a', 501)]] as $govde) {
            $this->client->jsonRequest('POST', "/huni/ziyaretler/{$ziyaret}/izin", $govde);
            self::assertResponseStatusCodeSame(422, (string) json_encode($govde));
        }
        self::assertSame(0, $this->say('huni_ziyaretleri WHERE izin_at IS NOT NULL'));

        $this->client->jsonRequest('POST', '/huni/ziyaretler/00000000-0000-4000-8000-000000000000/izin', []);
        self::assertResponseStatusCodeSame(404);
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler/{ziyaret}/izin', $this->client->getResponse());
    }

    public function testIzinGeriAlTiklamaVeBekleyenPaylasimVerisiniSiler(): void
    {
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, fbclid, ttclid, izin_at) VALUES ('keepchat', 'fb-x', 'tt-x', now()) RETURNING id");
        self::assertIsString($ziyaret);
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_revoke',
            'ziyaret_id' => $ziyaret,
            'ip_adresi' => '192.0.2.25',
            'user_agent' => 'Test browser',
        ]);
        $this->db->insert('reklam_donusumleri', [
            'saglayici' => 'meta', 'event_id' => 'cs_revoke', 'payload' => json_encode(['data' => ['private']]),
        ]);
        $this->db->insert('reklam_donusumleri', [
            'saglayici' => 'tiktok', 'event_id' => 'cs_already_sent', 'payload' => null, 'gonderildi_at' => date('c'),
        ]);

        $this->client->request('DELETE', "/huni/ziyaretler/{$ziyaret}/izin");

        self::assertResponseStatusCodeSame(204);
        Sozlesme::yanitiDogrula('DELETE', '/huni/ziyaretler/{ziyaret}/izin', $this->client->getResponse());
        self::assertSame(
            ['fbclid' => null, 'ttclid' => null, 'izin_at' => null],
            $this->db->fetchAssociative('SELECT fbclid, ttclid, izin_at FROM huni_ziyaretleri WHERE id = :id', ['id' => $ziyaret]),
        );
        self::assertSame(
            ['ip_adresi' => null, 'user_agent' => null],
            $this->db->fetchAssociative('SELECT host(ip_adresi) AS ip_adresi, user_agent FROM odeme_oturumlari WHERE stripe_checkout_session_id = :id', ['id' => 'cs_revoke']),
        );
        self::assertSame(
            ['payload' => null, 'son_hata' => 'izin geri alındı'],
            $this->db->fetchAssociative('SELECT payload, son_hata FROM reklam_donusumleri WHERE event_id = :id', ['id' => 'cs_revoke']),
        );
        self::assertSame(1, $this->say("reklam_donusumleri WHERE event_id = 'cs_already_sent' AND gonderildi_at IS NOT NULL"));
    }

    public function testIzinGeriAlBilinmeyenZiyaret404(): void
    {
        $this->client->request('DELETE', '/huni/ziyaretler/00000000-0000-4000-8000-000000000000/izin');
        self::assertResponseStatusCodeSame(404);
        Sozlesme::yanitiDogrula('DELETE', '/huni/ziyaretler/{ziyaret}/izin', $this->client->getResponse());
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

    /**
     * Ç34 + H6b: üretimde çekirdek Caddy'nin arkasında; vekil güvenilir değilse
     * bütün ziyaretçiler vekilin IP'sinden gelmiş görünür — reklam ağına giden
     * eşleşme IP'si ve hız sınırı anahtarı yanlış olur.
     */
    public function testVekilArkasindaGercekIstemciIpsiKullanilir(): void
    {
        $ziyaret = $this->ziyaretAc('keepchat');
        $this->db->executeStatement('UPDATE huni_ziyaretleri SET izin_at = now() WHERE id = :id', ['id' => $ziyaret]);

        $this->client->request('POST', '/checkout/sessions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ], (string) json_encode(['app' => 'keepchat', 'plan' => 'monthly', 'ziyaret' => $ziyaret]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('203.0.113.9', $this->db->fetchOne('SELECT host(ip_adresi) FROM odeme_oturumlari'));
    }

    /** Ç34: ziyaret ucu herkese açık — IP başına dakikada 120; aşan 429, başka IP etkilenmez. */
    public function testZiyaretUcuIpBasinaHizSinirli(): void
    {
        $ziyaret = function (string $ip): int {
            $this->client->request('POST', '/huni/ziyaretler', [], [], [
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => '172.18.0.5',
                'HTTP_X_FORWARDED_FOR' => $ip,
            ], (string) json_encode(['app' => 'keepchat']));

            return $this->client->getResponse()->getStatusCode();
        };
        for ($i = 0; $i < 120; ++$i) {
            self::assertSame(201, $ziyaret('203.0.113.20'), "istek {$i}");
        }

        self::assertSame(429, $ziyaret('203.0.113.20'));
        Sozlesme::yanitiDogrula('POST', '/huni/ziyaretler', $this->client->getResponse());
        self::assertSame(201, $ziyaret('203.0.113.21'));
        self::assertSame(121, $this->say('huni_ziyaretleri'));
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

    public function testCokluCevaplarKaydedilirVeTekrarGonderimdeEskiSecimlerSilinir(): void
    {
        $z = $this->ziyaretAc('keepchat');

        $this->ilerle($z, ['sira' => 1, 'adim' => 'kiminle', 'cevaplar' => ['aile', 'is']]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['aile', 'is'], $this->db->fetchFirstColumn('SELECT secenek FROM huni_cevaplari ORDER BY secenek'));

        $this->ilerle($z, ['sira' => 1, 'adim' => 'kiminle', 'cevaplar' => ['is']]);
        self::assertSame(['is'], $this->db->fetchFirstColumn('SELECT secenek FROM huni_cevaplari'));
        self::assertSame(1, $this->say('huni_adimlari'));
    }

    public function testSerbestYanitAyricaSaklanirVeBosYanittaOncekiDegerSilinir(): void
    {
        $z = $this->ziyaretAc('keepchat');
        $this->ilerle($z, ['sira' => 1, 'adim' => 'hedef', 'cevapMetni' => 'Daha iyi uyumak istiyorum']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([['soru' => 'hedef', 'cevap' => 'Daha iyi uyumak istiyorum']], $this->db->fetchAllAssociative('SELECT soru, cevap FROM huni_metin_cevaplari'));
        self::assertSame(0, $this->say('huni_cevaplari'));

        $this->ilerle($z, ['sira' => 1, 'adim' => 'hedef', 'cevapMetni' => '']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->say('huni_metin_cevaplari'));
    }

    public function testSerbestYanitSiniriVeTurDogrulanir(): void
    {
        $z = $this->ziyaretAc('keepchat');
        foreach ([['cevapMetni' => str_repeat('a', 501)], ['cevapMetni' => 7], ['cevapMetni' => "x\0y"], ['cevapMetni' => 'x', 'cevap' => 'a']] as $yanit) {
            $this->ilerle($z, ['sira' => 1, 'adim' => 'hedef'] + $yanit);
            self::assertResponseStatusCodeSame(422);
        }
        $this->ilerle($z, ['sira' => 1, 'adim' => 'hedef', 'cevapMetni' => str_repeat('ı', 500)]);
        self::assertResponseStatusCodeSame(204);
        $cevap = $this->db->fetchOne('SELECT cevap FROM huni_metin_cevaplari');
        self::assertIsString($cevap);
        self::assertSame(500, mb_strlen($cevap));
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
            ['sira' => 0, 'adim' => 'a', 'cevaplar' => []],
            ['sira' => 0, 'adim' => 'a', 'cevaplar' => ['a', 'a']],
            ['sira' => 0, 'adim' => 'a', 'cevaplar' => ['a', []]],
            ['sira' => 0, 'adim' => 'a', 'cevaplar' => ['a'], 'cevap' => 'a'],
        ] as $govde) {
            $this->ilerle($z, $govde);
            self::assertResponseStatusCodeSame(422, (string) json_encode($govde));
        }
        $this->ilerle($z, ['sira' => 50, 'adim' => str_repeat('a', 64)]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->say('huni_adimlari'));
    }

    /** H6a: izin ucu tarayıcıda CORS yüzünden düşüyordu (durum 0) — happy-dom testleri bunu görmez. */
    #[\PHPUnit\Framework\Attributes\TestWith(['ilerleme', 'POST'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['izin', 'POST'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['izin', 'DELETE'])]
    public function testZiyaretAltUclarindaCorsHuniKokenine(string $uc, string $method): void
    {
        $this->client->request('OPTIONS', "/huni/ziyaretler/00000000-0000-4000-8000-000000000000/{$uc}", [], [], [
            'HTTP_ORIGIN' => 'https://huni.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        self::assertSame('https://huni.test', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        self::assertStringContainsString($method, $this->client->getResponse()->headers->get('Access-Control-Allow-Methods', ''));
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
