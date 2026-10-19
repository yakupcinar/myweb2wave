<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
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
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly']);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $client->getResponse());
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
        self::assertSame([['price' => 'price_test_keepchat_aylik', 'quantity' => 1]], $istek['params']['line_items']);
        self::assertSame('https://huni.test/keepchat/basarili?session_id={CHECKOUT_SESSION_ID}', $istek['params']['success_url']);
        self::assertSame('https://huni.test/keepchat/iptal', $istek['params']['cancel_url']);
        // Webhook aboneliğin hangi uygulamaya ait olduğunu BURADAN okur (A4).
        self::assertSame(['app' => 'keepchat'], $istek['params']['metadata']);
        self::assertSame(['metadata' => ['app' => 'keepchat']], $istek['params']['subscription_data']);
    }

    public function testBozukJson400VeStripeaIstekGitmez(): void
    {
        $client = static::createClient();
        $client->request('POST', '/checkout/sessions', [], [], ['CONTENT_TYPE' => 'application/json'], '{bozuk');

        self::assertResponseStatusCodeSame(400);
        self::assertCount(0, $this->stripe->istekler);
    }

    public function testStripeaUlasilamazsa502(): void
    {
        // Ç16: 500 yerine ne olduğunu söyleyen 502; huni mesaj gösterir.
        $this->stripe->firlat = new ApiConnectionException('baglanti yok');
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly']);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(['error' => 'payment_provider_unavailable'], json_decode((string) $client->getResponse()->getContent(), true));
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $client->getResponse());
    }

    public function testCorsYalnizHunininKokenineIzinVerir(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/checkout/sessions', [], [], [
            'HTTP_ORIGIN' => 'https://huni.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);
        self::assertSame('https://huni.test', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        $client->request('OPTIONS', '/checkout/sessions', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:9999',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testWebhookYolundaCorsYok(): void
    {
        // Webhook'u yalnız Stripe çağırır; tarayıcıya açılmaz.
        $client = static::createClient();
        $client->request('OPTIONS', '/webhooks/stripe', [], [], [
            'HTTP_ORIGIN' => 'https://huni.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        self::assertNull($client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testBilinmeyenUygulama422VeStripeaIstekGitmez(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'olmayan', 'plan' => 'monthly']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'unknown_app'], json_decode((string) $client->getResponse()->getContent(), true));
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $client->getResponse());
        self::assertCount(0, $this->stripe->istekler);
    }

    public function testBilinmeyenPlan422VeStripeaIstekGitmez(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'yillik']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'unknown_plan'], json_decode((string) $client->getResponse()->getContent(), true));
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $client->getResponse());
        self::assertCount(0, $this->stripe->istekler);
    }
}
