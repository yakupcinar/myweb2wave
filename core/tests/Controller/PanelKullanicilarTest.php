<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * P5: Kullanıcılar (CRM) — abonelik başına satır, başlatan ziyaretin kaynağı,
 * kişi/bağlantı; e-posta maskeli (kullanıcı kararı, panel.md). Beklenenler
 * elle (§3).
 */
final class PanelKullanicilarTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, subscriptions, kisiler, baglantilar CASCADE');
        // Ziyaretin huni/sürümü yayınlanmış çift olmalı (yabancı anahtar) — sırası başka teste bağlı kalmasın.
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));

        // Ayşe: reklamdan (paid_social), kişi + uygulamaya bağlı, en yeni.
        $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, huni, surum, utm_source, utm_medium, utm_campaign) VALUES ('keepchat', 'genel', 1, 'meta', 'paid_social', 'ekim-1') RETURNING id");
        $this->db->executeStatement("INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id, stripe_subscription_id, tamamlandi_at) VALUES ('cs_a', :z, 'sub_a', '2026-11-01 10:00Z')", ['z' => $z]);
        $this->abonelik('sub_a', 'active', 'ayse@example.com', '2026-11-02 09:00Z', '2026-11-01 10:00Z');
        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('ayse@example.com') RETURNING id");
        $this->db->executeStatement("INSERT INTO baglantilar (app, harici_kullanici, kisi_id) VALUES ('keepchat', 'u1', :k)", ['k' => $kisi]);
        // Zeynep: organik bülten, kişi yok.
        $z = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, utm_source, utm_campaign) VALUES ('keepchat', 'bulten', 'kis') RETURNING id");
        $this->db->executeStatement("INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id, stripe_subscription_id) VALUES ('cs_c', :z, 'sub_c')", ['z' => $z]);
        $this->abonelik('sub_c', 'past_due', 'zeynep@x.com', '2026-11-01 09:00Z', null);
        // Mehmet: ziyareti yok (panel dışı ödeme), iptal.
        $this->abonelik('sub_b', 'canceled', 'mehmet@ornek.com', '2026-10-30 09:00Z', null);
    }

    public function testGirisYapmadanGirise(): void
    {
        $this->client->request('GET', '/panel/api/kullanicilar');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('GET', '/panel/kullanicilar');
        self::assertResponseRedirects('/panel/giris');
    }

    public function testSatirlarKaynakKisiMaskeliEposta(): void
    {
        $this->girisYap();

        $this->client->request('GET', '/panel/kullanicilar');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->client->getCrawler()->filter('#panel-uygulama'));

        $j = $this->liste('');
        self::assertSame(['active', 'canceled', 'past_due'], $j['durumlar']);
        self::assertSame([
            ['sub_a', 'keepchat', 'active', 'a***@example.com', '2026-11-02T09:00:00+00:00', '2026-11-01T10:00:00+00:00', true, 'meta', 'paid_social', 'ekim-1', 'genel', true, true, false, null, null],
            ['sub_c', 'keepchat', 'past_due', 'z***@x.com', '2026-11-01T09:00:00+00:00', null, false, 'bulten', null, 'kis', null, false, false, false, null, null],
            ['sub_b', 'keepchat', 'canceled', 'm***@ornek.com', '2026-10-30T09:00:00+00:00', null, false, null, null, null, null, false, false, false, null, null],
        ], array_map(static fn (array $s): array => array_values($s), $j['satirlar']));
        self::assertStringNotContainsString('ayse@', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    public function testAramaVeDurumSuzgeci(): void
    {
        $this->girisYap();

        $abonelikler = fn (string $sorgu): array => array_column($this->liste($sorgu)['satirlar'], 'abonelik');
        self::assertSame(['sub_a'], $abonelikler('?ara=AYSE'));
        self::assertSame(['sub_a'], $abonelikler('?ara=Meta'), 'kaynakta');
        self::assertSame(['sub_c'], $abonelikler('?ara=kis'), 'kampanyada');
        self::assertSame(['sub_b'], $abonelikler('?ara=ornek.com'));
        // Joker karakter yazı olarak: % her şeyi bulmaz.
        self::assertSame([], $abonelikler('?ara=%25'));
        self::assertSame([], $abonelikler('?ara=_'));
        self::assertSame(['sub_b'], $abonelikler('?durum=canceled'));
        self::assertSame(['sub_a', 'sub_c', 'sub_b'], $abonelikler('?ara=&durum='));
        self::assertSame([], $abonelikler('?ara=ayse&durum=canceled'));
    }

    /** @return array{azami: int, durumlar: list<string>, satirlar: list<array<string, mixed>>} */
    private function liste(string $sorgu): array
    {
        $this->client->request('GET', '/panel/api/kullanicilar'.$sorgu);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/kullanicilar', $this->client->getResponse());
        /** @var array{azami: int, durumlar: list<string>, satirlar: list<array<string, mixed>>} $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $j;
    }

    private function abonelik(string $id, string $durum, string $email, string $guncelleme, ?string $odeme): void
    {
        $this->db->insert('subscriptions', [
            'stripe_subscription_id' => $id, 'stripe_customer_id' => 'cus_'.$id, 'status' => $durum, 'email' => $email,
            'app' => 'keepchat', 'updated_at' => $guncelleme, 'odeme_tamamlandi_at' => $odeme,
        ]);
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
