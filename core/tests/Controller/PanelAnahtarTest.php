<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** A5a: panelden kişisel API anahtarı yönetimi — oluştur (bir kez), MCP'de çalışır, iptal edince çalışmaz. */
final class PanelAnahtarTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE api_anahtarlari, mcp_cagrilari');
        $sinir = static::getContainer()->get('cache.rate_limiter');
        self::assertTrue(method_exists($sinir, 'clear') && $sinir->clear());
    }

    public function testGirisYapmadanKapali(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/anahtarlar');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/anahtarlar', content: '{"ad":"x"}');
        self::assertResponseRedirects('/panel/giris');
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM api_anahtarlari'));
    }

    /** Oluşturulan anahtar bir kez döner, MCP'de çalışır; listede yalnız önek; iptal edilince MCP reddeder. */
    public function testOlusturMcpdeCalisirIptalEdilinceCalismaz(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->istek('POST', '/panel/api/uygulamalar/keepchat/anahtarlar', $jeton, ['ad' => 'Claude Code — dizüstü']);
        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/anahtarlar', $this->client->getResponse());
        /** @var array{id: int, ad: string, anahtar: string} $yeni sözleşmede doğrulandı */
        $yeni = $this->json();
        self::assertSame(200, $this->mcp($yeni['anahtar']), 'yeni anahtar MCP\'de çalışır');

        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/anahtarlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/anahtarlar', $this->client->getResponse());
        /** @var array{mcpAdresi: string, anahtarlar: list<array{ad: string, onek: string, iptal: bool}>} $liste sözleşmede doğrulandı */
        $liste = $this->json();
        self::assertSame('https://cekirdek.test/mcp', $liste['mcpAdresi']);
        self::assertStringNotContainsString(substr($yeni['anahtar'], 11), (string) $this->client->getResponse()->getContent(), 'liste anahtarı göstermez');
        self::assertSame([['ad' => 'Claude Code — dizüstü', 'onek' => substr($yeni['anahtar'], 0, 11), 'iptal' => false]], array_map(static fn (array $a): array => ['ad' => $a['ad'], 'onek' => $a['onek'], 'iptal' => $a['iptal']], $liste['anahtarlar']));

        $this->istek('DELETE', '/panel/api/uygulamalar/keepchat/anahtarlar/'.$yeni['id'], $jeton);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(401, $this->mcp($yeni['anahtar']), 'iptal edilen anahtar reddedilir');
        $this->istek('DELETE', '/panel/api/uygulamalar/keepchat/anahtarlar/'.$yeni['id'], $jeton);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCsrfAdVeUygulama(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->istek('POST', '/panel/api/uygulamalar/keepchat/anahtarlar', '', ['ad' => 'x']);
        self::assertResponseStatusCodeSame(403);
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/anahtarlar', $jeton, ['ad' => '  ']);
        self::assertResponseStatusCodeSame(422);
        $this->istek('POST', '/panel/api/uygulamalar/baska/anahtarlar', $jeton, ['ad' => 'x']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM api_anahtarlari'));
        $this->istek('DELETE', '/panel/api/uygulamalar/keepchat/anahtarlar/1', '');
        self::assertResponseStatusCodeSame(403);
    }

    private function mcp(string $anahtar): int
    {
        $this->client->request('POST', '/mcp', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$anahtar, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], content: (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']]]));

        return $this->client->getResponse()->getStatusCode();
    }

    /** @param array<string, string> $govde */
    private function istek(string $yontem, string $adres, string $jeton, array $govde = []): void
    {
        $this->client->request($yontem, $adres, server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: [] === $govde ? null : (string) json_encode($govde));
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/anahtarlar');
        self::assertResponseIsSuccessful();

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
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
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
