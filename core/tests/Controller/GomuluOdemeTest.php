<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Atif\Ziyaretler;
use App\Billing\Fiyatlar;
use App\Billing\Planlar;
use App\Billing\UlkeBulucu;
use App\Controller\CheckoutController;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Psr\Log\NullLogger;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * M2d: ödeme paywall sayfasında gömülü (A14). Karar: istemci Stripe.js'i
 * yükleyebildiyse gomulu ister → embedded_page oturumu + clientSecret ve
 * yayımlanabilir anahtar; sunucuda anahtar yoksa ya da istenmezse eski yol
 * (barındırılan sayfa url'si) — ödeme hiçbir durumda kırılmaz. stripe-mock
 * client_secret döndürmediği için gömülü yanıt kayıttan verilir.
 */
final class GomuluOdemeTest extends WebTestCase
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

    public function testGomuluOturumSirVeAnahtarDoner(): void
    {
        $client = static::createClient();
        $this->stripe->yanitlar['/v1/checkout/sessions'] = ['id' => 'cs_test_gomulu', 'object' => 'checkout.session', 'client_secret' => 'cs_test_gomulu_secret_abc', 'url' => null, 'ui_mode' => 'embedded_page'];
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'gomulu' => true]);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $client->getResponse());
        self::assertSame(['clientSecret' => 'cs_test_gomulu_secret_abc', 'anahtar' => 'pk_test_ornek'], json_decode((string) $client->getResponse()->getContent(), true));
        $p = $this->oturumIstegi();
        self::assertSame('embedded_page', $p['ui_mode']);
        self::assertSame('https://huni.test/keepchat/basarili?session_id={CHECKOUT_SESSION_ID}', $p['return_url']);
        // Gömülüde Stripe success/cancel kabul etmez; iptal = paneli kapatmak.
        self::assertArrayNotHasKey('success_url', $p);
        self::assertArrayNotHasKey('cancel_url', $p);
    }

    /** Stripe sır döndürmezse (beklenmez) istemciye 502 — boş sırla form açılmaz. */
    public function testSirYoksa502(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'gomulu' => true]);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(['error' => 'payment_provider_unavailable'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /** İstenmezse ya da sunucuda yayımlanabilir anahtar yoksa barındırılan sayfa (eski yol). */
    public function testAnahtarYoksaYaDaIstenmezseUrl(): void
    {
        $client = static::createClient();
        $client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly']);
        self::assertResponseStatusCodeSame(201);
        self::assertArrayNotHasKey('ui_mode', $this->oturumIstegi());

        $c = static::getContainer();
        $anahtarsiz = new CheckoutController(
            self::servis($c->get(StripeClient::class), StripeClient::class),
            new NullLogger(),
            self::servis($c->get(Ziyaretler::class), Ziyaretler::class),
            self::servis($c->get(Planlar::class), Planlar::class),
            self::servis($c->get(Fiyatlar::class), Fiyatlar::class),
            self::servis($c->get(UlkeBulucu::class), UlkeBulucu::class),
            ['keepchat' => []],
            'https://huni.test',
            '',
        );
        $this->stripe->istekler = [];
        $yanit = $anahtarsiz(Request::create('/checkout/sessions', 'POST', content: '{"app":"keepchat","plan":"monthly","gomulu":true}'));
        self::assertSame(201, $yanit->getStatusCode());
        $j = json_decode((string) $yanit->getContent(), true);
        self::assertIsArray($j);
        self::assertArrayHasKey('url', $j);
        self::assertArrayNotHasKey('ui_mode', $this->oturumIstegi());
    }

    /**
     * Ödeme oturumu isteği — önce fiyat okunabilir (para birimi her zaman
     * zorlanıyor), sıra değil adres ölçülür.
     *
     * @return array<mixed>
     */
    private function oturumIstegi(): array
    {
        foreach ($this->stripe->istekler as $i) {
            if ('post' === $i['method'] && str_ends_with($i['url'], '/v1/checkout/sessions')) {
                return $i['params'];
            }
        }
        self::fail('ödeme oturumu isteği yok');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $sinif
     *
     * @return T
     */
    private static function servis(mixed $s, string $sinif): object
    {
        self::assertInstanceOf($sinif, $s);

        return $s;
    }
}
