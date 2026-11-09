<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Olay\Olaylar;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M7b (A20): panelden giden webhook hedefleri — sır yalnız eklemede, adres
 * https, olaylar sözlükten, uygulama başına sınır, etkin / pasif, sil,
 * teslim günlüğü ve yeniden gönder; başka uygulamanın hedefine dokunulmaz.
 */
final class PanelWebhookTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private string $jeton = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE webhook_hedefleri, webhook_teslimleri, huni_ziyaretleri, ziyaret_deneyleri, olaylar CASCADE');
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->client->request('GET', '/panel/webhooklar');
        self::assertResponseIsSuccessful('doğrudan adres de açılır (tuzak §51)');
        $this->jeton = (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    /**
     * @param array<string, mixed>|null $govde
     *
     * @return array<mixed>
     */
    private function istek(string $yontem, string $yol, ?array $govde = null, ?string $jeton = null): array
    {
        $this->client->request($yontem, '/panel/api/uygulamalar/keepchat/webhooklar'.$yol, server: ['HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton], content: null === $govde ? null : (string) json_encode($govde));

        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($j) ? $j : [];
    }

    public function testEkleSirBirKezListedeSonDortHane(): void
    {
        $eklenen = $this->istek('POST', '', ['adres' => 'https://alici.example.com/kanca', 'olaylar' => ['odeme.fatura', 'abonelik.durum']]);
        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/webhooklar', $this->client->getResponse());
        self::assertIsString($eklenen['sir'] ?? null);
        self::assertStringStartsWith('whsec_', $eklenen['sir']);

        $liste = $this->istek('GET', '');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/webhooklar', $this->client->getResponse());
        self::assertSame(array_keys(Olaylar::SOZLUK), $liste['olaylar']);
        // Sözlük sırasıyla; sır listede yok, son 4 hanesi var.
        /** @var list<array{adres: string, olaylar: list<string>, aktif: bool, sirSonu: string}> $hedefler */
        $hedefler = $liste['hedefler'];
        self::assertSame([['https://alici.example.com/kanca', ['abonelik.durum', 'odeme.fatura'], true, substr($eklenen['sir'], -4)]],
            array_map(static fn (array $h): array => [$h['adres'], $h['olaylar'], $h['aktif'], $h['sirSonu']], $hedefler));
        self::assertStringNotContainsString($eklenen['sir'], (string) $this->client->getResponse()->getContent());
    }

    public function testDogrulamaVeSinir(): void
    {
        foreach ([
            ['http://alici.example.com', ['odeme.fatura']],
            ['https://alici.example.com', []],
            ['https://alici.example.com', ['olmayan.olay']],
            ['https://alici.example.com', ['odeme.fatura', 'odeme.fatura']],
        ] as [$adres, $olaylar]) {
            $this->istek('POST', '', ['adres' => $adres, 'olaylar' => $olaylar]);
            self::assertResponseStatusCodeSame(422, $adres.' '.implode(',', $olaylar));
        }
        foreach (range(1, 10) as $i) {
            $this->istek('POST', '', ['adres' => "https://a{$i}.example.com", 'olaylar' => ['odeme.fatura']]);
            self::assertResponseStatusCodeSame(201);
        }
        $this->istek('POST', '', ['adres' => 'https://a11.example.com', 'olaylar' => ['odeme.fatura']]);
        self::assertResponseStatusCodeSame(422);
        $this->istek('POST', '', ['adres' => 'https://x.example.com', 'olaylar' => ['odeme.fatura']], 'yanlis');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDurumSilVeBaskaUygulama(): void
    {
        $id = $this->istek('POST', '', ['adres' => 'https://a.example.com', 'olaylar' => ['odeme.fatura']])['id'] ?? null;
        self::assertIsString($id);
        $baska = $this->db->fetchOne("INSERT INTO webhook_hedefleri (app, adres, olaylar, sir) VALUES ('baska', 'https://b.example.com', '{odeme.fatura}', 'whsec_x') RETURNING id");
        self::assertIsString($baska);

        $this->istek('PUT', "/{$id}", ['aktif' => false]);
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->db->fetchOne('SELECT aktif FROM webhook_hedefleri WHERE id = ?', [$id]));
        $this->istek('PUT', "/{$baska}", ['aktif' => false]);
        self::assertResponseStatusCodeSame(404);
        $this->istek('DELETE', "/{$baska}");
        self::assertResponseStatusCodeSame(404);
        $this->istek('DELETE', "/{$id}");
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['baska'], $this->db->fetchFirstColumn('SELECT app FROM webhook_hedefleri'));
    }

    /** Günlük yeniden eskiye; yeniden gönder vazgeçilmiş teslimi sıfırlar (hemen, deneme 0). */
    public function testGunlukVeYenidenGonder(): void
    {
        $id = $this->istek('POST', '', ['adres' => 'https://a.example.com', 'olaylar' => ['huni.ziyaret']])['id'] ?? null;
        self::assertIsString($id);
        $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('keepchat') RETURNING id::text");
        self::assertIsString($z);
        Olaylar::ekle($this->db, $z, 'huni.ziyaret');
        $olay = $this->db->fetchOne('SELECT max(id) FROM olaylar');
        $teslim = $this->db->fetchOne("INSERT INTO webhook_teslimleri (hedef_id, olay_id, durum, deneme, son_hata, sonraki_deneme_at) VALUES (?, ?, 'vazgecildi', 10, 'HTTP 500', now() + interval '1 hour') RETURNING id", [$id, $olay]);
        self::assertIsInt($teslim);

        $g = $this->istek('GET', "/{$id}/teslimler");
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/webhooklar/{id}/teslimler', $this->client->getResponse());
        /** @var list<array{olay: string, durum: string, deneme: int, sonHata: ?string}> $teslimler */
        $teslimler = $g['teslimler'];
        self::assertSame([['huni.ziyaret', 'vazgecildi', 10, 'HTTP 500']], array_map(static fn (array $t): array => [$t['olay'], $t['durum'], $t['deneme'], $t['sonHata']], $teslimler));

        $this->istek('POST', "/{$id}/teslimler/{$teslim}/yeniden");
        self::assertResponseStatusCodeSame(204);
        self::assertSame(['bekliyor', 0, null, true], array_values((array) $this->db->fetchAssociative('SELECT durum, deneme, son_hata, sonraki_deneme_at <= now() FROM webhook_teslimleri')));
        $this->istek('POST', "/{$id}/teslimler/999999/yeniden");
        self::assertResponseStatusCodeSame(404);
    }
}
