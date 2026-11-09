<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Huni\Tanim;
use App\Huni\Tema;
use App\Mcp\ApiAnahtarlari;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\SahteEkranCekici;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A5a (B6): MCP uç noktası — kişisel API anahtarı (Bearer), Streamable HTTP
 * (2025-11-25 el sıkışması), A0 araçları, kapsam, kayıt. İstemci yerine
 * JSON-RPC elle (gerçek HTTP ile aynı biçim; ölçüm mcp-a5a.md).
 */
final class McpTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private Huniler $huniler;
    private ApiAnahtarlari $anahtarlar;
    private string $oturum = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE odeme_faturalari, reklam_harcamalari, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, medya, uygulama_baglamlari, api_anahtarlari, mcp_cagrilari');
        $sinir = static::getContainer()->get('cache.rate_limiter');
        self::assertTrue(method_exists($sinir, 'clear') && $sinir->clear());
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        $anahtarlar = static::getContainer()->get(ApiAnahtarlari::class);
        self::assertInstanceOf(ApiAnahtarlari::class, $anahtarlar);
        $this->anahtarlar = $anahtarlar;
    }

    public function testAnahtarsizGecersizIptalliReddedilir(): void
    {
        $anahtar = $this->anahtarlar->olustur('keepchat', 'deneme');

        foreach (['', 'Bearer mw2w_yanlis', 'Bearer '.substr($anahtar['anahtar'], 5), $anahtar['anahtar']] as $baslik) {
            $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()], $baslik);
            self::assertResponseStatusCodeSame(401, "başlık: {$baslik}");
            self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="MyWeb2Wave MCP"');
        }
        // Sorgu dizgesinde anahtar kabul edilmez (günlüklere sızar).
        $this->client->request('POST', '/mcp?api_key='.$anahtar['anahtar'], server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);

        self::assertTrue($this->anahtarlar->iptal('keepchat', $anahtar['id']));
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()], 'Bearer '.$anahtar['anahtar']);
        self::assertResponseStatusCodeSame(401, 'iptal edilen anahtar');
    }

    /** Anahtarın kendisi saklanmaz: yalnız SHA-256 özeti ve görünen önek. */
    public function testAnahtarDuzMetinSaklanmaz(): void
    {
        $a = $this->anahtarlar->olustur('keepchat', 'deneme');

        self::assertMatchesRegularExpression('/^mw2w_[0-9a-f]{48}$/', $a['anahtar']);
        $satir = $this->db->fetchAssociative('SELECT ozet, onek FROM api_anahtarlari');
        self::assertSame(['ozet' => hash('sha256', $a['anahtar']), 'onek' => substr($a['anahtar'], 0, 11)], $satir);
        self::assertStringNotContainsString(substr($a['anahtar'], 11), (string) json_encode($this->db->fetchAllAssociative('SELECT * FROM api_anahtarlari')));
    }

    /** Uçtan uca: el sıkışma → araç listesi → oku → taslağa yaz; çağrılar kaydedilir; yayınlanmaz. */
    public function testElSikismaAraclarVeTaslagaYazma(): void
    {
        $this->db->insert('uygulama_baglamlari', ['app' => 'keepchat', 'kitle' => 'Aileler']);
        $a = $this->anahtarlar->olustur('keepchat', 'Claude Code');
        $bearer = 'Bearer '.$a['anahtar'];

        $ilk = $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()], $bearer);
        self::assertResponseIsSuccessful();
        self::assertNotSame('', $this->oturum, 'Mcp-Session-Id');
        // Talimat + A4 ürün bağlamı.
        self::assertStringContainsString('Yalnız TASLAĞA', self::metin(self::yol($ilk, 'result', 'instructions')));
        self::assertStringContainsString('Hedef kitle: Aileler', self::metin(self::yol($ilk, 'result', 'instructions')));
        // Ç71: panel asistanıyla aynı tema yönergesi.
        self::assertStringContainsString(Tema::aiYonergesi(), self::metin(self::yol($ilk, 'result', 'instructions')));
        self::assertStringContainsString('"planın hazırlanıyor" yükleme', self::metin(self::yol($ilk, 'result', 'instructions')));
        // T1: blok kataloğu ve uydurma sayı/yorum yasağı panel istemiyle aynı.
        self::assertStringContainsString(Tanim::EKRAN_YONERGESI, self::metin(self::yol($ilk, 'result', 'instructions')));
        self::assertStringContainsString(\App\Huni\TasarimYonergesi::METIN, self::metin(self::yol($ilk, 'result', 'instructions')));
        $this->rpc(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $bearer);
        self::assertResponseStatusCodeSame(202);

        $liste = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $bearer);
        $araclar = self::yol($liste, 'result', 'tools');
        self::assertIsArray($araclar);
        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi', 'ekran_goruntusu', 'stok_gorsel_ara', 'stok_gorsel_al', 'yeni_huni',
            'rapor_ozet', 'rapor_kaynaklar', 'rapor_kohort', 'rapor_kampanyalar', 'rapor_cevaplar', 'deney_sonuclari'], array_column($araclar, 'name'));
        $sema = array_column($araclar, 'inputSchema', 'name')['taslak_kaydet'] ?? null;
        self::assertIsArray(self::yol($sema, '$defs', 'HuniTanimi'));

        $oku = $this->rpc(self::cagri(3, 'huni_oku', ['huni' => 'genel']), $bearer);
        $imza = self::yol($oku, 'result', 'structuredContent', 'imza');
        $yaz = $this->rpc(self::cagri(4, 'taslak_kaydet', ['huni' => 'genel', 'tanim' => self::tanim('MCP ile'), 'imza' => $imza]), $bearer);

        self::assertFalse(self::yol($yaz, 'result', 'isError'));
        self::assertTrue(self::yol($yaz, 'result', 'structuredContent', 'kaydedildi'));
        self::assertSame('MCP ile', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
        self::assertNull($this->huniler->huniler('keepchat')[0]['yayinda'], 'yayınlanmadı');
        self::assertSame(
            [['huni_oku', false], ['taslak_kaydet', false]],
            array_map(static fn (array $s): array => [$s['arac'], $s['hata']], $this->db->fetchAllAssociative('SELECT arac, hata FROM mcp_cagrilari ORDER BY id')),
        );
        self::assertNotNull($this->db->fetchOne('SELECT son_kullanim_at FROM api_anahtarlari'));
    }

    /**
     * M7c1 (A21): rapor araçları yalnız okur ve anahtarın uygulamasıyla
     * sınırlı — başka uygulamanın ziyaret, cevap ve geliri sayılmaz. Meta
     * harcaması hesap düzeyinde (bölünmez), gelir bölünür.
     */
    public function testRaporAraclariUygulamayaSinirli(): void
    {
        foreach (['keepchat', 'keepchat', 'baska'] as $i => $app) {
            // 2 gün önce: kohortun ilk 24 saati dolmuş olsun (gelir ölçülsün).
            $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, utm_source, utm_medium, utm_campaign, fbclid, izin_at, created_at) VALUES (:app, 'meta', 'cpc', 'ekim', 'fb', now(), now() - interval '2 days') RETURNING id::text", ['app' => $app]);
            $this->db->insert('olaylar', ['ziyaret_id' => $z, 'app' => $app, 'ad' => 'huni.ziyaret']);
            $this->db->insert('huni_cevaplari', ['ziyaret_id' => $z, 'soru' => 'kiminle', 'secenek' => 'aile']);
            $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => "cs_{$i}", 'ziyaret_id' => $z, 'stripe_subscription_id' => "sub_{$i}", 'abonelik_at' => '2026-11-01 10:00Z', 'tutar_kucuk' => 999, 'para_birimi' => 'eur']);
            $this->db->executeStatement("INSERT INTO odeme_faturalari (stripe_invoice_id, stripe_subscription_id, app, tutar_kucuk, para_birimi, odendi_at) VALUES (:i, :s, :app, 1000, 'eur', now() - interval '2 days' + interval '1 hour')", ['i' => "in_{$i}", 's' => "sub_{$i}", 'app' => $app]);
        }
        $this->db->executeStatement("INSERT INTO reklam_harcamalari (saglayici, kampanya_id, kampanya_adi, gun, harcama_kucuk, para_birimi) VALUES ('meta', 'c', 'ekim', (now() AT TIME ZONE 'UTC')::date, 4000, 'eur')");
        $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'rapor')['anahtar'];
        $this->elSikis($bearer);

        $liste = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $bearer);
        $araclar = array_column((array) self::yol($liste, 'result', 'tools'), null, 'name');
        self::assertTrue(self::yol($araclar, 'rapor_ozet', 'annotations', 'readOnlyHint'));
        $sonuc = fn (string $ad): mixed => self::yol($this->rpc(self::cagri(3, $ad, ['gun' => 30]), $bearer), 'result', 'structuredContent');

        self::assertSame(['ziyaret' => 2, 'odemeye_gecti' => 2, 'deneme' => 0, 'odedi' => 2], self::yol($sonuc('rapor_ozet'), 'sayimlar', 'reklam'));
        self::assertSame(['kabul' => 2, 'red' => 0, 'secmeyen' => 0], self::yol($sonuc('rapor_ozet'), 'izinler'));
        self::assertSame(2, array_sum(array_column((array) self::yol($sonuc('rapor_ozet'), 'gunluk'), 'ziyaret')));
        self::assertSame([['paraBirimi' => 'EUR', 'tutar' => 20]], self::yol($sonuc('rapor_kaynaklar'), 'kaynaklar', 0, 'gelir'));
        self::assertSame(2, self::yol($sonuc('rapor_kohort'), 'kohort', 0, 'ziyaret'));
        // 2 × 10 EUR / 2 ziyaret — başka uygulamanın faturası eklenseydi 15.
        self::assertSame([['paraBirimi' => 'EUR', 'tutar' => 10]], self::yol($sonuc('rapor_kohort'), 'kohort', 0, 'pencereler', 0, 'kisiBasi'));
        self::assertSame(2, self::yol($sonuc('rapor_cevaplar'), 'cevaplar', 0, 'sayi'));
        // Harcama 40 EUR (hesap düzeyi), gelir yalnız bu uygulamanın 20 EUR → 0,5.
        self::assertSame([2, 0.5], [self::yol($sonuc('rapor_kampanyalar'), 'kampanyalar', 0, 'ziyaret'), self::yol($sonuc('rapor_kampanyalar'), 'kampanyalar', 0, 'roas')]);
        self::assertTrue(self::yol($this->rpc(self::cagri(4, 'deney_sonuclari', ['ad' => 'yok']), $bearer), 'result', 'isError'));
        // Şema dışı aralık araç çalışmadan SDK'da reddedilir (JSON-RPC geçersiz parametre).
        self::assertSame(-32602, self::yol($this->rpc(self::cagri(5, 'rapor_ozet', ['gun' => 5]), $bearer), 'error', 'code'));
    }

    /**
     * M7c2 (A21): yazma araçları yalnız yönetim izinli anahtarda — izinsiz
     * anahtar araçları görmez, çağıramaz. Yıkıcı olanlar işaretli.
     */
    public function testYonetimAraclariYalnizIzinliAnahtarda(): void
    {
        $yazma = ['abonelikler_listele', 'abonelik_iptal_planla', 'abonelik_iptal_geri_al', 'abonelik_son_fatura_iade', 'deney_kaydet', 'deney_durum'];
        $izinsiz = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'okur')['anahtar'];
        $this->elSikis($izinsiz);
        /** @var list<string> $adlar */
        $adlar = array_column((array) self::yol($this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $izinsiz), 'result', 'tools'), 'name');
        self::assertSame([], array_values(array_intersect($yazma, $adlar)));
        self::assertArrayHasKey('error', $this->rpc(self::cagri(3, 'abonelik_iptal_planla', ['abonelik' => 'sub_1']), $izinsiz), 'izinsiz anahtar çağıramaz');

        $this->oturum = '';
        $izinli = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'yonetici', true)['anahtar'];
        $this->elSikis($izinli);
        $araclar = array_column((array) self::yol($this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $izinli), 'result', 'tools'), null, 'name');
        self::assertSame($yazma, array_values(array_intersect(array_keys($araclar), $yazma)));
        self::assertSame([true, true, true, false], [
            self::yol($araclar, 'abonelik_son_fatura_iade', 'annotations', 'destructiveHint'), self::yol($araclar, 'abonelik_iptal_planla', 'annotations', 'destructiveHint'),
            self::yol($araclar, 'abonelikler_listele', 'annotations', 'readOnlyHint'), self::yol($araclar, 'deney_kaydet', 'annotations', 'destructiveHint'),
        ]);
    }

    /**
     * Abonelik araçları panelle aynı servis; başka uygulamanın aboneliği "yok"
     * (Stripe'a gidilmez); liste yalnız bu uygulama, e-posta maskeli.
     */
    public function testAbonelikAraclari(): void
    {
        $this->db->executeStatement('TRUNCATE subscriptions CASCADE');
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_k', 'stripe_customer_id' => 'cus_k', 'status' => 'active', 'email' => 'ayse@example.com', 'app' => 'keepchat']);
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_b', 'stripe_customer_id' => 'cus_b', 'status' => 'active', 'email' => 'baska@example.com', 'app' => 'baska']);
        $stripe = new KayitliStripeIstemcisi();
        $stripe->yanitlar = ['post /v1/subscriptions/sub_k' => ['id' => 'sub_k', 'object' => 'subscription', 'cancel_at_period_end' => true]];
        ApiRequestor::setHttpClient($stripe);
        try {
            $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'yonetici', true)['anahtar'];
            $this->elSikis($bearer);

            $liste = $this->rpc(self::cagri(2, 'abonelikler_listele', []), $bearer);
            /** @var list<array<string, mixed>> $abonelikler */
            $abonelikler = self::yol($liste, 'result', 'structuredContent', 'abonelikler');
            self::assertSame([['abonelik' => 'sub_k', 'durum' => 'active', 'email' => 'a***@example.com']], array_map(
                static fn (array $a): array => array_intersect_key($a, ['abonelik' => 1, 'durum' => 1, 'email' => 1]),
                $abonelikler,
            ));
            self::assertStringNotContainsString('ayse@example.com', (string) json_encode($liste));

            self::assertFalse(self::yol($this->rpc(self::cagri(3, 'abonelik_iptal_planla', ['abonelik' => 'sub_k']), $bearer), 'result', 'isError'));
            self::assertTrue($this->db->fetchOne("SELECT iptal_donem_sonu FROM subscriptions WHERE stripe_subscription_id = 'sub_k'"));
            // Stripe'a giden değer (sahte yanıt hep true döner — yazılan durum tek başına ölçmez, kırma Y8).
            self::assertSame(['cancel_at_period_end' => 'true'], array_intersect_key($stripe->istekler[0]['params'] ?? [], ['cancel_at_period_end' => 1]));
            $stripe->istekler = [];
            $baska = $this->rpc(self::cagri(4, 'abonelik_iptal_planla', ['abonelik' => 'sub_b']), $bearer);
            self::assertSame([true, 'bilinmeyen abonelik'], [self::yol($baska, 'result', 'isError'), self::yol($baska, 'result', 'structuredContent', 'hata')]);
            self::assertTrue(self::yol($this->rpc(self::cagri(5, 'abonelik_son_fatura_iade', ['abonelik' => 'sub_b']), $bearer), 'result', 'isError'));
            self::assertSame([], $stripe->istekler, 'başka uygulamanın aboneliğinde Stripe\'a gidilmez');
            self::assertSame('iade edilecek tahsil edilmiş fatura yok', self::yol($this->rpc(self::cagri(6, 'abonelik_son_fatura_iade', ['abonelik' => 'sub_k']), $bearer), 'result', 'structuredContent', 'hata'));
            self::assertContains('abonelik_iptal_planla', $this->db->fetchFirstColumn('SELECT arac FROM mcp_cagrilari'));
        } finally {
            ApiRequestor::setHttpClient(new CurlClient());
        }
    }

    /** Deney araçları: taslak kaydedilir; yayında olmayan huniyle başlatılamaz; olmayan deney hata. */
    public function testDeneyAraclari(): void
    {
        $this->db->executeStatement('TRUNCATE deneyler CASCADE');
        $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'yonetici', true)['anahtar'];
        $this->elSikis($bearer);

        $kayit = $this->rpc(self::cagri(2, 'deney_kaydet', ['ad' => 'baslik', 'tur' => 'icerik', 'huni' => 'genel', 'varyantlar' => [['ad' => 'a', 'pay' => 50], ['ad' => 'b', 'pay' => 50]]]), $bearer);
        self::assertFalse(self::yol($kayit, 'result', 'isError'));
        self::assertSame('taslak', $this->db->fetchOne("SELECT durum FROM deneyler WHERE app = 'keepchat' AND ad = 'baslik'"));
        $hatali = $this->rpc(self::cagri(3, 'deney_kaydet', ['ad' => 'x', 'tur' => 'icerik', 'huni' => 'genel', 'varyantlar' => [['ad' => 'a', 'pay' => 30], ['ad' => 'b', 'pay' => 30]]]), $bearer);
        self::assertTrue(self::yol($hatali, 'result', 'isError'), 'paylar 100 değil');
        $yok = $this->rpc(self::cagri(4, 'deney_durum', ['ad' => 'yok', 'durum' => 'aktif']), $bearer);
        self::assertSame('deney yok: yok', self::yol($yok, 'result', 'structuredContent', 'hata'));
        $yayinda = $this->rpc(self::cagri(5, 'deney_durum', ['ad' => 'baslik', 'durum' => 'aktif']), $bearer);
        self::assertSame("'genel' hunisi yayında değil — önce yayınla.", self::yol($yayinda, 'result', 'structuredContent', 'hata'));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Deney')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        self::assertFalse(self::yol($this->rpc(self::cagri(6, 'deney_durum', ['ad' => 'baslik', 'durum' => 'aktif']), $bearer), 'result', 'isError'));
        self::assertSame('aktif', $this->db->fetchOne("SELECT durum FROM deneyler WHERE app = 'keepchat' AND ad = 'baslik'"));
    }

    /** Ç72: ekran görüntüsü MCP'de görüntü içeriği (JPEG, base64) olarak döner. */
    public function testEkranGoruntusuGoruntuIcerigiDoner(): void
    {
        SahteEkranCekici::sifirla();
        $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'x')['anahtar'];
        $this->elSikis($bearer);

        $s = $this->rpc(self::cagri(2, 'ekran_goruntusu', ['huni' => 'genel']), $bearer);

        self::assertFalse(self::yol($s, 'result', 'isError'));
        self::assertSame(
            ['type' => 'image', 'data' => base64_encode(SahteEkranCekici::BAYTLAR), 'mimeType' => 'image/jpeg'],
            self::yol($s, 'result', 'content', 1),
        );
        self::assertSame('text', self::yol($s, 'result', 'content', 0, 'type'));
        self::assertSame('karsilama', self::yol($s, 'result', 'structuredContent', 'adim'));
        self::assertCount(1, SahteEkranCekici::$adresler);
    }

    /** Araç hatası isError ile modele; şema dışı alan araç ÇALIŞMADAN reddedilir; yayınlama aracı yok. */
    public function testHatalarVeKapsam(): void
    {
        $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'x')['anahtar'];
        $this->elSikis($bearer);

        $yok = $this->rpc(self::cagri(2, 'huni_oku', ['huni' => 'yok']), $bearer);
        self::assertTrue(self::yol($yok, 'result', 'isError'));
        self::assertSame(["'yok' adında huni yok"], self::yol($yok, 'result', 'structuredContent', 'hatalar'));

        $fazla = $this->rpc(self::cagri(3, 'huni_oku', ['huni' => 'genel', 'app' => 'baska']), $bearer);
        self::assertSame(-32602, self::yol($fazla, 'error', 'code'));
        $yayin = $this->rpc(self::cagri(4, 'yayinla', ['huni' => 'genel']), $bearer);
        self::assertSame(-32602, self::yol($yayin, 'error', 'code'));
        self::assertSame(['huni_oku'], $this->db->fetchFirstColumn('SELECT arac FROM mcp_cagrilari'), 'reddedilen çağrılar araca ulaşmadı');
    }

    /** DNS rebinding: izinli olmayan Host reddedilir (herkese açık adres MCP_IZINLI_HOSTLAR'a yazılır). */
    public function testIzinsizHostReddedilir(): void
    {
        $bearer = 'Bearer '.$this->anahtarlar->olustur('keepchat', 'x')['anahtar'];
        $this->client->request('POST', '/mcp', server: ['HTTP_HOST' => 'saldirgan.example', 'HTTP_AUTHORIZATION' => $bearer, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], content: (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()]));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testAnahtarBasinaHizSiniri(): void
    {
        $a = $this->anahtarlar->olustur('keepchat', 'x');
        $sinir = static::getContainer()->get('limiter.mcp');
        self::assertInstanceOf(\Symfony\Component\RateLimiter\RateLimiterFactory::class, $sinir);
        $sinir->create((string) $a['id'])->consume(120);

        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()], 'Bearer '.$a['anahtar']);

        self::assertResponseStatusCodeSame(429);
    }

    private function elSikis(string $bearer): void
    {
        $this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => self::ilk()], $bearer);
        $this->rpc(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $bearer);
    }

    /**
     * @param array<string, mixed> $govde
     *
     * @return array<mixed>
     */
    private function rpc(array $govde, string $yetki): array
    {
        $sunucu = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if ('' !== $yetki) {
            $sunucu['HTTP_AUTHORIZATION'] = $yetki;
        }
        if ('' !== $this->oturum) {
            $sunucu['HTTP_MCP_SESSION_ID'] = $this->oturum;
            $sunucu['HTTP_MCP_PROTOCOL_VERSION'] = '2025-11-25';
        }
        $this->client->request('POST', '/mcp', server: $sunucu, content: (string) json_encode($govde));
        $yanit = $this->client->getInternalResponse();
        $oturum = $yanit->getHeader('Mcp-Session-Id');
        if ('' === $this->oturum && \is_string($oturum) && '' !== $oturum) {
            $this->oturum = $oturum;
        }
        $metin = (string) $yanit->getContent();

        return '' === $metin ? [] : (array) json_decode($metin, true);
    }

    /**
     * @param array<string, mixed> $argumanlar
     *
     * @return array<string, mixed>
     */
    private static function cagri(int $id, string $ad, array $argumanlar): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => $ad, 'arguments' => $argumanlar]];
    }

    /** @return array<string, mixed> */
    private static function ilk(): array
    {
        return ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']];
    }

    private static function yol(mixed $kok, string|int ...$anahtarlar): mixed
    {
        foreach ($anahtarlar as $a) {
            self::assertIsArray($kok);
            self::assertArrayHasKey($a, $kok);
            $kok = $kok[$a];
        }

        return $kok;
    }

    private static function metin(mixed $v): string
    {
        self::assertIsString($v);

        return $v;
    }

    /** @return array<string, mixed> */
    private static function tanim(string $baslik): array
    {
        return ['sayfaBasligi' => $baslik, 'adimlar' => [
            ['id' => 'karsilama', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [['tur' => 'metin', 'metin' => 'Başla.']], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Plan', 'metin' => 'Abone ol.', 'dugme' => 'Abone ol'],
        ]];
    }
}
