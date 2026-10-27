<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Huni\Tema;
use App\Mcp\ApiAnahtarlari;
use Doctrine\DBAL\Connection;
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
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya, uygulama_baglamlari, api_anahtarlari, mcp_cagrilari');
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
        $this->rpc(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $bearer);
        self::assertResponseStatusCodeSame(202);

        $liste = $this->rpc(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $bearer);
        $araclar = self::yol($liste, 'result', 'tools');
        self::assertIsArray($araclar);
        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi', 'yeni_huni'], array_column($araclar, 'name'));
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
            ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Başla.', 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Plan', 'metin' => 'Abone ol.', 'dugme' => 'Abone ol'],
        ]];
    }
}
