<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Olay\Olaylar;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** M7c1 (A21): olay akışı API'si — uygulama anahtarı, imleçle sayfalama, kişisel veri yok, yalnız bu uygulama. */
final class OlayAkisiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri CASCADE');
        foreach (['keepchat', 'keepchat', 'baska', 'keepchat'] as $app) {
            $z = $this->db->fetchOne('INSERT INTO huni_ziyaretleri (app) VALUES (?) RETURNING id::text', [$app]);
            self::assertIsString($z);
            Olaylar::ekle($this->db, $z, 'huni.ziyaret', 'keepchat' === $app ? ['huni' => 'genel'] : []);
        }
    }

    /** @return array<string, mixed> */
    private function iste(string $sorgu = '', string $anahtar = 'test-anahtar-keepchat'): array
    {
        $this->client->request('GET', '/apps/keepchat/olaylar'.$sorgu, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$anahtar]);
        /** @var array<string, mixed> $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $j;
    }

    public function testImlecleSayfalarYalnizBuUygulama(): void
    {
        $ilk = $this->iste('?sinir=2');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/apps/{app}/olaylar', $this->client->getResponse());
        self::assertTrue($ilk['devam']);
        self::assertIsString($ilk['imlec']);
        $ikinci = $this->iste('?sinir=2&imlec='.rawurlencode($ilk['imlec']));
        self::assertFalse($ikinci['devam']);
        self::assertIsArray($ilk['olaylar']);
        self::assertIsArray($ikinci['olaylar']);
        self::assertCount(3, [...$ilk['olaylar'], ...$ikinci['olaylar']], 'başka uygulamanınki yok');
        // Son sayfadan sonra yeni olay: aynı imleçle o gelir (Ç119).
        $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('keepchat') RETURNING id::text");
        self::assertIsString($z);
        Olaylar::ekle($this->db, $z, 'izin.verildi');
        self::assertIsString($ikinci['imlec']);
        $yeni = $this->iste('?imlec='.rawurlencode($ikinci['imlec']));
        self::assertSame(['izin.verildi'], array_column((array) $yeni['olaylar'], 'ad'));
        // Boş veri JSON nesnesi (sözleşme: object), dizi değil.
        self::assertStringContainsString('"veri":{}', (string) $this->client->getResponse()->getContent());
    }

    public function testYetkiVeBozukImlec(): void
    {
        $this->iste('', 'yanlis');
        self::assertResponseStatusCodeSame(401);
        $this->iste('?imlec=bozuk');
        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('GET', '/apps/{app}/olaylar', $this->client->getResponse());
    }
}
