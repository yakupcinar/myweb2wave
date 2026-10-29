<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Billing\Fiyatlar;
use App\Huni\Huniler;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Ç19/Ç21: paywall fiyatı tek kaynaktan — Stripe fiyat nesnesi, çekirdek
 * üzerinden huni yanıtında. Değerler stripe-mock'un sabit fiyatından
 * (2000 USD/ay); ana birime çeviri burada elle: 2000 / 100 = 20 (tuzak §3).
 */
final class FiyatTest extends WebTestCase
{
    private KernelBrowser $client;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya');
        // Önbellek testler arası taşınırsa "Stripe kapalı" testi eski fiyatla yeşil kalırdı.
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);

        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', [
            'sayfaBasligi' => 'X',
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Kısa', 'dugme' => 'Başla'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => 'Aylık plan', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testPaywallPlanininFiyatiStripetanGelirVeOnbellektenOkunur(): void
    {
        $this->client->request('GET', '/huni/keepchat');

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertSame(
            ['monthly' => ['tutar' => 20, 'paraBirimi' => 'USD', 'aralik' => 'month']],
            $this->fiyatlar(),
        );
        self::assertSame(1, $this->istekSayisi());
        self::assertStringEndsWith('/v1/prices/price_test_keepchat_aylik', $this->stripe->istekler[0]['url']);

        $this->client->request('GET', '/huni/keepchat');
        self::assertSame(1, $this->istekSayisi(), 'ikinci okuma önbellekten');
    }

    public function testStripeeUlasilamazsaHuniYineAcilirFiyatsiz(): void
    {
        $this->stripe->firlat = new ApiConnectionException('ağ yok');

        $this->client->request('GET', '/huni/keepchat');

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertNull($this->fiyatlar());

        // Hata önbelleğe yazılmaz: Stripe dönünce fiyat gelir.
        $this->stripe->firlat = null;
        $this->client->request('GET', '/huni/keepchat');
        self::assertNotNull($this->fiyatlar());
    }

    public function testYalnizTekAralikliYinelenenFiyatGosterilir(): void
    {
        $fiyat = static fn (array $tekrar, int $tutar = 999, string $para = 'eur'): ?array => Fiyatlar::oku(['unit_amount' => $tutar, 'currency' => $para, 'recurring' => $tekrar]);

        self::assertSame(['tutar' => 9.99, 'paraBirimi' => 'EUR', 'aralik' => 'month'], $fiyat(['interval' => 'month', 'interval_count' => 1]));
        // Ondalıksız para birimi bölünmez (1200 JPY = ¥1.200).
        self::assertSame(['tutar' => 1200, 'paraBirimi' => 'JPY', 'aralik' => 'year'], $fiyat(['interval' => 'year', 'interval_count' => 1], 1200, 'jpy'));
        self::assertNull($fiyat(['interval' => 'month', 'interval_count' => 3]), '"3 ayda bir" "/ ay" diye yazılmaz');
        self::assertNull(Fiyatlar::oku(['unit_amount' => 999, 'currency' => 'eur', 'recurring' => null]), 'tek seferlik fiyat');
    }

    private function istekSayisi(): int
    {
        return \count($this->stripe->istekler);
    }

    /** @return array<mixed>|null */
    private function fiyatlar(): ?array
    {
        $yanit = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($yanit);
        $fiyatlar = $yanit['fiyatlar'] ?? null;

        return \is_array($fiyatlar) ? $fiyatlar : null;
    }
}
