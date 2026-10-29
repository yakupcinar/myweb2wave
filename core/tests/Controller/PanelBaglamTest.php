<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** A4: uygulama başına ürün bağlamı — panel uçları (sözleşme) ve kısıt. */
final class PanelBaglamTest extends WebTestCase
{
    private const DOLU = ['urun' => 'Sade birebir mesajlaşma', 'kategori' => 'Mesajlaşma', 'kitle' => 'Aileler', 'ton' => 'Sıcak, sade', 'yasaklar' => 'Rakip adı verme'];

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE uygulama_baglamlari');
    }

    public function testGirisYapmadanKapali(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/baglam');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/baglam', content: '{}');
        self::assertResponseRedirects('/panel/giris');
    }

    public function testListeVeBosBaglam(): void
    {
        $this->girisYap();

        $this->client->request('GET', '/panel/api/uygulamalar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar', $this->client->getResponse());
        self::assertSame(['uygulamalar' => ['keepchat']], $this->json());

        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/baglam');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/baglam', $this->client->getResponse());
        self::assertSame(['urun' => '', 'kategori' => '', 'kitle' => '', 'ton' => '', 'yasaklar' => ''], $this->json());
    }

    /** Sayfa kabuğunun jetonuyla kaydedilir; kenar boşlukları kırpılır; yeniden yazılır. */
    public function testKabukJetonuylaKaydedilir(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->kaydet(['kitle' => '  Aileler  '] + self::DOLU, $jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/baglam', $this->client->getResponse());
        self::assertSame(self::DOLU, $this->json());

        $this->kaydet(['ton' => 'Resmî'] + self::DOLU, $jeton);
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/baglam');
        self::assertSame('Resmî', $this->json()['ton']);
        self::assertSame(1, $this->db->fetchOne('SELECT count(*) FROM uygulama_baglamlari'));
    }

    public function testCsrfUzunlukAlanUygulama(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->kaydet(self::DOLU, '');
        self::assertResponseStatusCodeSame(403);
        $this->kaydet(['kitle' => str_repeat('ç', 501)] + self::DOLU, $jeton);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['hatalar' => ['kitle: en çok 500 karakter']], $this->json());
        $this->kaydet(['fiyat' => '9'] + self::DOLU, $jeton);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('PUT', '/panel/api/uygulamalar/baska/baglam', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: '{}');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM uygulama_baglamlari'));
        // Sınır dahil: tam 500 karakter (çok baytlı) kabul.
        $this->kaydet(['kitle' => str_repeat('ç', 500)] + self::DOLU, $jeton);
        self::assertResponseIsSuccessful();
    }

    /** Tuzak §4: uygulamadaki uzunluk denetimi kısıtı görünmez kılar — kısıt doğrudan. */
    public function testVeritabaniKisiti(): void
    {
        $this->expectException(DriverException::class);
        $this->db->insert('uygulama_baglamlari', ['app' => 'keepchat', 'kategori' => str_repeat('a', 101)]);
    }

    /** @param array<string, string> $govde */
    private function kaydet(array $govde, string $jeton): void
    {
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/baglam', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: (string) json_encode($govde));
    }

    /** Panel uygulamasının kabuğu (/panel/baglam) jetonu taşır. */
    private function jeton(): string
    {
        $this->client->request('GET', '/panel/baglam');
        self::assertResponseIsSuccessful();
        $jeton = (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
        self::assertNotSame('', $jeton);

        return $jeton;
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
