<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M2a: plan seçicili paywall uçtan uca — yayınlanır, huni yanıtı seçicideki
 * her planın fiyatını panel adıyla taşır (kart başlığı), sözleşmeye uyar;
 * seçilen plan ödemeyi açar.
 */
final class PaywallPlanlariTest extends WebTestCase
{
    protected function tearDown(): void
    {
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement("DELETE FROM planlar WHERE anahtar <> 'monthly'");
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testSeciciliPaywallYayinlanirFiyatlarAdlarla(): void
    {
        $client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $db->insert('planlar', ['app' => 'keepchat', 'anahtar' => 'yearly', 'ad' => 'Yıllık', 'stripe_urun' => 'prod_y', 'stripe_fiyat' => 'price_yillik']);
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($stripe);
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly', 'planlar' => ['yearly', 'monthly'], 'onerilen' => 'yearly'],
        ]]));
        self::assertIsInt($huniler->yayinla('keepchat', 'genel'));

        $client->request('GET', '/huni/keepchat');
        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $client->getResponse());
        /** @var array{fiyatlar: array<string, array{ad: string}>, tanim: array{adimlar: list<array<string, mixed>>}} $j */
        $j = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['monthly' => 'Aylık', 'yearly' => 'Yıllık'], array_map(static fn (array $f): string => $f['ad'], $j['fiyatlar']));
        self::assertSame(['yearly', 'monthly'], $j['tanim']['adimlar'][1]['planlar'], 'sıra korunur');

        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'yearly']);
        self::assertResponseStatusCodeSame(201);
        $oturum = array_values(array_filter($stripe->istekler, static fn (array $i): bool => str_ends_with($i['url'], '/v1/checkout/sessions')))[0]['params'];
        self::assertIsArray($oturum['line_items']);
        self::assertSame([['price' => 'price_yillik', 'quantity' => 1]], $oturum['line_items']);
    }
}
