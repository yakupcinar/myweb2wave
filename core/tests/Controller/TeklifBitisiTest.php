<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M2b: teklifin gerçek bitişi (A15: herkes için aynı, süre dolunca teklif
 * GERÇEKTEN kalkar) ve paywall şart onayının ödemeye kanıt olarak gitmesi.
 * Bekçi tek: Planlar::teklifler süresi geçmişi döndürmez — paywall, panel ve
 * ödeme aynı yerden okur.
 */
final class TeklifBitisiTest extends WebTestCase
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
        $this->sifirla();
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        $this->sifirla();
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    /** Bitiş saat dilimiyle yazılır, listede UTC ISO döner (sözleşme). */
    public function testBitisYazilirListedeUtc(): void
    {
        $bitis = (new \DateTimeImmutable('+2 days', new \DateTimeZone('Europe/Istanbul')))->setTime(23, 59);
        $this->girisYap();
        $this->teklif(['tur' => 'deneme', 'gun' => 7, 'bitis' => $bitis->format(\DATE_ATOM)]);

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/planlar/{anahtar}/teklif', $this->client->getResponse());
        /** @var array{planlar: list<array{fiyat: array{teklif: array<string, mixed>}}>} $j */
        $j = $this->json();
        // Bağımsız beklenti (§3): İstanbul 23:59 = UTC 20:59.
        self::assertSame(['tur' => 'deneme', 'gun' => 7, 'bitis' => $bitis->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d').'T20:59:00Z'], $j['planlar'][0]['fiyat']['teklif']);
    }

    /** @return iterable<string, array{array<string, mixed>, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        yield 'geçmiş' => [['tur' => 'deneme', 'gun' => 7, 'bitis' => '2020-01-01T00:00:00+00:00'], 'Bitiş gelecekte'];
        yield '1 yıldan sonra' => [['tur' => 'deneme', 'gun' => 7, 'bitis' => (new \DateTimeImmutable('+2 years'))->format(\DATE_ATOM)], 'en çok 1 yıl'];
        yield 'saat dilimsiz' => [['tur' => 'deneme', 'gun' => 7, 'bitis' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i:s')], 'saat dilimli'];
        yield 'bozuk' => [['tur' => 'deneme', 'gun' => 7, 'bitis' => 'yarın'], 'saat dilimli'];
        yield 'teklifsiz' => [['tur' => null, 'bitis' => (new \DateTimeImmutable('+2 days'))->format(\DATE_ATOM)], 'Teklifsiz planda bitiş'];
    }

    /** @param array<string, mixed> $govde */
    #[DataProvider('gecersizler')]
    public function testGecersizBitisStripeaGitmez(array $govde, string $hata): void
    {
        $this->girisYap();
        $this->teklif($govde);

        self::assertResponseStatusCodeSame(422);
        $h = $this->json()['hatalar'] ?? null;
        self::assertIsArray($h);
        self::assertStringContainsString($hata, implode(' ', array_map(static fn (mixed $x): string => \is_string($x) ? $x : '', $h)));
        self::assertSame([], $this->stripe->istekler);
        self::assertNull($this->db->fetchOne("SELECT teklif_turu FROM planlar WHERE anahtar = 'monthly'"));
    }

    /**
     * Süresi geçen teklif HER YERDEN kalkar: huni fiyatı teklifsiz, panel
     * teklifsiz, ödeme denemesiz. Süresi geçmemiş aynı teklif hepsinde var
     * (kontrol grubu — sorgu yanlışsa ikisi de düşerdi).
     */
    public function testSuresiGecenTeklifHerYerdenKalkar(): void
    {
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null, 'fitness'));
        self::assertIsInt($huniler->yayinla('keepchat', 'genel'));

        foreach (["now() + interval '1 hour'" => true, "now() - interval '1 second'" => false] as $an => $etkin) {
            $this->db->executeStatement("UPDATE planlar SET teklif_turu = 'deneme', teklif_gun = 7, teklif_bitis = {$an} WHERE anahtar = 'monthly'");

            $this->client->request('GET', '/huni/keepchat');
            /** @var array{fiyatlar: array{monthly: array<string, mixed>}} $j */
            $j = $this->json();
            self::assertSame($etkin, isset($j['fiyatlar']['monthly']['teklif']), "huni ({$an})");

            $this->stripe->istekler = [];
            $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly']);
            self::assertResponseStatusCodeSame(201);
            $oturum = $this->oturum();
            self::assertSame($etkin, isset($oturum['subscription_data']['trial_period_days']), "ödeme ({$an})");
        }
    }

    public function testVeritabaniTeklifsizBitisiReddeder(): void
    {
        try {
            $this->db->executeStatement("UPDATE planlar SET teklif_bitis = now() + interval '1 day' WHERE anahtar = 'monthly'");
            self::fail('teklifsiz planda bitiş yazıldı');
        } catch (DriverException $e) {
            self::assertStringContainsString('planlar_teklif_bitis_teklifli', $e->getMessage());
        }
    }

    /** Şart onayı Stripe'ta kanıt (oturum + abonelik); onaysız istekte iz yok. */
    public function testSartOnayiOdemedeKanit(): void
    {
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'sartOnayi' => true]);
        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/checkout/sessions', $this->client->getResponse());
        $o = $this->oturum();
        self::assertSame(['app' => 'keepchat', 'sart_onayi' => '1'], $o['metadata']);
        self::assertSame(['app' => 'keepchat', 'sart_onayi' => '1'], $o['subscription_data']['metadata']);

        $this->stripe->istekler = [];
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly', 'sartOnayi' => 'evet']);
        self::assertSame(['app' => 'keepchat'], $this->oturum()['metadata'], 'yalnız true kanıt sayılır');
    }

    private function sifirla(): void
    {
        $this->db->executeStatement("UPDATE planlar SET teklif_turu = NULL, teklif_gun = NULL, teklif_yuzde = NULL, stripe_teklif = NULL, teklif_bitis = NULL WHERE app = 'keepchat' AND anahtar = 'monthly'");
    }

    /** @return array{metadata: array<string, string>, subscription_data: array{metadata: array<string, string>, trial_period_days?: int|string}} */
    private function oturum(): array
    {
        foreach ($this->stripe->istekler as $i) {
            if ('post' === $i['method'] && str_ends_with($i['url'], '/v1/checkout/sessions')) {
                /** @var array{metadata: array<string, string>, subscription_data: array{metadata: array<string, string>, trial_period_days?: int|string}} $p */
                $p = $i['params'];

                return $p;
            }
        }
        self::fail('ödeme oturumu açılmadı');
    }

    /** @param array<string, mixed> $govde */
    private function teklif(array $govde): void
    {
        $this->client->request('GET', '/panel/anahtarlar');
        $jeton = (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/teklif', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: (string) json_encode($govde));
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        /** @var array<string, mixed>|null $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($j);

        return $j;
    }
}
