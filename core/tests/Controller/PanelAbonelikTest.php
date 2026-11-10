<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M6a (A19): panelden dönem sonunda iptal / geri alma ve son faturanın tam
 * iadesi. Stripe'a giden değerler kayıttan ölçülür (stripe-mock değerleri
 * bilmez); Stripe'ın yanıtındaki durum yazılır.
 */
final class PanelAbonelikTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE subscriptions, odeme_faturalari, fatura_odemeleri, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari CASCADE');
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_x', 'stripe_customer_id' => 'cus_x', 'status' => 'active', 'email' => 'a@example.com', 'app' => 'keepchat']);
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testDonemSonundaIptalVeGeriAl(): void
    {
        $jeton = $this->girisVeJeton();
        $this->stripe->yanitlar = ['post /v1/subscriptions/sub_x' => ['id' => 'sub_x', 'object' => 'subscription', 'cancel_at_period_end' => true]];
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iptal', $jeton);
        self::assertResponseStatusCodeSame(204);
        // stripe-php boolean'ı form alanında metin gönderir.
        self::assertSame([['post', 'cancel_at_period_end', 'true']], $this->stripeIstekleri('/v1/subscriptions/sub_x', 'cancel_at_period_end'));
        self::assertTrue($this->iptalPlanli());

        $this->stripe->istekler = [];
        $this->stripe->yanitlar = ['post /v1/subscriptions/sub_x' => ['id' => 'sub_x', 'object' => 'subscription', 'cancel_at_period_end' => false]];
        $this->istek('DELETE', '/panel/api/abonelikler/sub_x/iptal', $jeton);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([['post', 'cancel_at_period_end', 'false']], $this->stripeIstekleri('/v1/subscriptions/sub_x', 'cancel_at_period_end'));
        self::assertFalse($this->iptalPlanli());
    }

    /** Stripe'ın yanıtı yazılır, istenen değil: Stripe planlamadıysa panel planlı göstermez. */
    public function testStripeYanitiYazilir(): void
    {
        $jeton = $this->girisVeJeton();
        $this->stripe->yanitlar = ['post /v1/subscriptions/sub_x' => ['id' => 'sub_x', 'object' => 'subscription', 'cancel_at_period_end' => false]];
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iptal', $jeton);

        self::assertFalse($this->iptalPlanli());
    }

    public function testJetonsuzBilinmeyenVeGirissiz(): void
    {
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iptal', 'x');
        self::assertResponseRedirects('/panel/giris');

        $jeton = $this->girisVeJeton();
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iptal', 'yanlis');
        self::assertResponseStatusCodeSame(403);
        $this->istek('POST', '/panel/api/abonelikler/sub_yok/iptal', $jeton);
        self::assertResponseStatusCodeSame(404);
        Sozlesme::yanitiDogrula('POST', '/panel/api/abonelikler/{abonelik}/iptal', $this->client->getResponse());
        self::assertSame([], $this->stripe->istekler, 'Stripe\'a hiçbir istek gitmedi');
    }

    /**
     * Son tahsil edilen faturanın KALANI iade edilir (daha önce 300 iade edilmişse
     * 999 − 300); ödeme kimliği fatura ödemesinden. İade hemen yazılır, ziyarete
     * odeme.iade olayı; ikinci istek "zaten iade" — Stripe'a gitmez.
     */
    public function testSonFaturaninKalaniIadeEdilir(): void
    {
        $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('keepchat') RETURNING id::text");
        self::assertIsString($z);
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_x', 'ziyaret_id' => $z, 'stripe_subscription_id' => 'sub_x']);
        $this->db->insert('odeme_faturalari', ['stripe_invoice_id' => 'in_eski', 'stripe_subscription_id' => 'sub_x', 'app' => 'keepchat', 'tutar_kucuk' => 1299, 'para_birimi' => 'eur', 'odendi_at' => '2026-10-01 10:00Z']);
        $this->db->insert('odeme_faturalari', ['stripe_invoice_id' => 'in_son', 'stripe_subscription_id' => 'sub_x', 'app' => 'keepchat', 'tutar_kucuk' => 999, 'iade_kucuk' => 300, 'para_birimi' => 'eur', 'odendi_at' => '2026-11-01 10:00Z']);
        $jeton = $this->girisVeJeton();
        $this->stripe->yanitlar = [
            'get /v1/invoice_payments' => ['object' => 'list', 'data' => [
                ['id' => 'inpay_1', 'object' => 'invoice_payment', 'status' => 'canceled', 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_iptal']],
                ['id' => 'inpay_2', 'object' => 'invoice_payment', 'status' => 'paid', 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_son']],
            ], 'has_more' => false, 'url' => '/v1/invoice_payments'],
            'post /v1/refunds' => ['id' => 're_1', 'object' => 'refund', 'amount' => 699, 'status' => 'succeeded'],
        ];

        $this->istek('POST', '/panel/api/abonelikler/sub_x/iade', $jeton);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('in_son', $this->stripeIstekleri('/v1/invoice_payments', 'invoice')[0][2] ?? null);
        $iade = $this->stripeIstekleri('/v1/refunds', 'payment_intent', 'amount');
        self::assertSame([['post', 'payment_intent', 'pi_son', 'amount', 699]], $iade);
        self::assertSame([['in_eski', 0], ['in_son', 999]], array_map(static fn (array $f): array => array_values($f), $this->db->fetchAllAssociative('SELECT stripe_invoice_id, iade_kucuk FROM odeme_faturalari ORDER BY 1')));
        self::assertSame(['odeme.iade', '{"tutar": 699, "paraBirimi": "eur"}'], array_values((array) $this->db->fetchAssociative("SELECT ad, veri::text FROM olaylar WHERE ziyaret_id = :z AND ad = 'odeme.iade'", ['z' => $z])));

        $this->stripe->istekler = [];
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iade', $jeton);
        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('POST', '/panel/api/abonelikler/{abonelik}/iade', $this->client->getResponse());
        self::assertSame([], $this->stripe->istekler);
    }

    /** Liste: iptal planı, dönem sonu ve SON faturanın tutarı / iadesi (ana birim). */
    public function testListedeIptalVeSonFatura(): void
    {
        $this->db->executeStatement("UPDATE subscriptions SET iptal_donem_sonu = true, donem_sonu = '2026-12-01 10:00Z' WHERE stripe_subscription_id = 'sub_x'");
        $this->db->insert('odeme_faturalari', ['stripe_invoice_id' => 'in_eski', 'stripe_subscription_id' => 'sub_x', 'app' => 'keepchat', 'tutar_kucuk' => 1299, 'para_birimi' => 'eur', 'odendi_at' => '2026-10-01 10:00Z']);
        $this->db->insert('odeme_faturalari', ['stripe_invoice_id' => 'in_son', 'stripe_subscription_id' => 'sub_x', 'app' => 'keepchat', 'tutar_kucuk' => 999, 'iade_kucuk' => 300, 'para_birimi' => 'eur', 'odendi_at' => '2026-11-01 10:00Z']);
        $this->girisVeJeton();
        $this->client->request('GET', '/panel/api/kullanicilar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/kullanicilar', $this->client->getResponse());
        /** @var array{satirlar: list<array<string, mixed>>} $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        $satir = $j['satirlar'][0] ?? [];

        self::assertSame([true, '2026-12-01T10:00:00+00:00', ['paraBirimi' => 'EUR', 'tutar' => 9.99, 'iade' => 3, 'odendi' => '2026-11-01T10:00:00+00:00']], [$satir['iptalDonemSonu'] ?? null, $satir['donemSonu'] ?? null, $satir['sonFatura'] ?? null]);
    }

    public function testFaturasizIadeYok(): void
    {
        $jeton = $this->girisVeJeton();
        $this->istek('POST', '/panel/api/abonelikler/sub_x/iade', $jeton);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['hatalar' => ['iade edilecek tahsil edilmiş fatura yok']], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    private function iptalPlanli(): bool
    {
        return true === $this->db->fetchOne("SELECT iptal_donem_sonu FROM subscriptions WHERE stripe_subscription_id = 'sub_x'");
    }

    /** @return list<list<mixed>> [yöntem, alan1, değer1, …] yolu eşleşen istekler */
    private function stripeIstekleri(string $yol, string ...$alanlar): array
    {
        $sonuc = [];
        foreach ($this->stripe->istekler as $i) {
            if (str_ends_with(explode('?', $i['url'])[0], $yol)) {
                parse_str((string) parse_url($i['url'], \PHP_URL_QUERY), $sorgu);
                $p = $i['params'] + $sorgu;
                $satir = [$i['method']];
                foreach ($alanlar as $a) {
                    array_push($satir, $a, $p[$a] ?? null);
                }
                $sonuc[] = $satir;
            }
        }

        return $sonuc;
    }

    private function girisVeJeton(): string
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->client->request('GET', '/panel/anahtarlar');
        self::assertResponseIsSuccessful();

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    private function istek(string $yontem, string $adres, string $jeton): void
    {
        $this->client->request($yontem, $adres, server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
    }
}
