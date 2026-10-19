<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutControllerTest extends WebTestCase
{
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testAylikPlanIcinAbonelikOturumuAcar(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['plan' => 'monthly']);

        self::assertResponseStatusCodeSame(201);
        $govde = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($govde);
        self::assertIsString($govde['url'] ?? null);
        self::assertStringStartsWith('https://', $govde['url']);

        // Değerler sunucu yapılandırmasından (.env.test) — istemciden değil.
        self::assertCount(1, $this->stripe->istekler);
        $istek = $this->stripe->istekler[0];
        self::assertSame('post', $istek['method']);
        self::assertStringEndsWith('/v1/checkout/sessions', $istek['url']);
        self::assertSame('subscription', $istek['params']['mode']);
        self::assertSame([['price' => 'price_test_aylik', 'quantity' => 1]], $istek['params']['line_items']);
        self::assertSame('https://huni.test/basarili?session_id={CHECKOUT_SESSION_ID}', $istek['params']['success_url']);
        self::assertSame('https://huni.test/iptal', $istek['params']['cancel_url']);
    }

    public function testBozukJson400VeStripeaIstekGitmez(): void
    {
        $client = static::createClient();
        $client->request('POST', '/checkout/sessions', [], [], ['CONTENT_TYPE' => 'application/json'], '{bozuk');

        self::assertResponseStatusCodeSame(400);
        self::assertCount(0, $this->stripe->istekler);
    }

    public function testBilinmeyenPlan422VeStripeaIstekGitmez(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['plan' => 'yillik']);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->stripe->istekler);
    }
}
