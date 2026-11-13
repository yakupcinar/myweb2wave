<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Etikete özel huni (A8): tek adres, kurallar seçer. */
final class HuniSecimTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private Huniler $huniler;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        foreach (['genel', 'fitness', 'is'] as $ad) {
            self::assertSame([], $this->huniler->yeniHuni('keepchat', $ad, null));
            self::assertSame([], $this->huniler->taslakKaydet('keepchat', $ad, self::tanim($ad)));
            self::assertSame(1, $this->huniler->yayinla('keepchat', $ad));
        }
    }

    public function testIlkHuniVarsayilanKuralYoksaHerkesOnuGorur(): void
    {
        self::assertSame('genel', $this->sec([]));
        self::assertSame('genel', $this->sec(['k' => 'fitness']));
    }

    public function testKuralEslesirseOHuniBuyukKucukHarfDuyarsiz(): void
    {
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'k', ' Fitness ', 'fitness'));

        self::assertSame('fitness', $this->sec(['k' => 'FITNESS']));
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertSame('genel', $this->sec(['k' => 'fitnes']));
        self::assertSame('genel', $this->sec(['utm_campaign' => 'fitness']), 'başka parametre tutmamalı');
    }

    public function testIlkTutanKuralKazanirSiraDegisinceSonucDegisir(): void
    {
        $this->huniler->kuralEkle('keepchat', 'utm_campaign', 'ekim', 'fitness');
        $this->huniler->kuralEkle('keepchat', 'k', 'is', 'is');
        $ikisi = ['utm_campaign' => 'ekim', 'k' => 'is'];

        self::assertSame('fitness', $this->sec($ikisi));
        $ikinci = $this->huniler->kurallar('keepchat')[1]['id'];
        self::assertTrue($this->huniler->kuralTasi('keepchat', $ikinci, -1));
        self::assertSame('is', $this->sec($ikisi));
        self::assertSame([1, 2], array_column($this->huniler->kurallar('keepchat'), 'sira'));
        self::assertFalse($this->huniler->kuralTasi('keepchat', $ikinci, -1), 'en üstteki daha yukarı gidemez');
    }

    public function testYayinlanmamisHuniyeGidenKuralAtlanir(): void
    {
        $this->huniler->yeniHuni('keepchat', 'taslak-only', null);
        $this->huniler->kuralEkle('keepchat', 'k', 'yeni', 'taslak-only');

        self::assertSame('genel', $this->sec(['k' => 'yeni']));
    }

    public function testDogrudanHuniParametresiTestAdresi(): void
    {
        self::assertSame('is', $this->sec(['huni' => 'is']));
        self::assertSame('genel', $this->sec(['huni' => 'olmayan']));
    }

    public function testVarsayilanDegisirTekVarsayilanKisittir(): void
    {
        self::assertTrue($this->huniler->varsayilanYap('keepchat', 'is'));
        self::assertSame('is', $this->sec([]));
        self::assertSame(['is'], $this->db->fetchFirstColumn("SELECT ad FROM huniler WHERE app = 'keepchat' AND varsayilan"));

        // Tuzak §4: servis önce hepsini kaldırıyor; kısıt yine de ikinciyi reddetmeli.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->db->executeStatement("UPDATE huniler SET varsayilan = true WHERE app = 'keepchat' AND ad = 'genel'");
    }

    public function testKuralDogrulamasi(): void
    {
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'k', 'a', 'fitness'));
        self::assertStringContainsString('zaten bir kural var', implode('', $this->huniler->kuralEkle('keepchat', 'k', 'A', 'is')));
        self::assertStringContainsString('parametre', implode('', $this->huniler->kuralEkle('keepchat', 'fbclid', 'x', 'is')));
        self::assertStringContainsString('1–200', implode('', $this->huniler->kuralEkle('keepchat', 'k', '  ', 'is')));
        self::assertStringContainsString('huni yok', implode('', $this->huniler->kuralEkle('keepchat', 'k', 'b', 'olmayan')));
        self::assertCount(1, $this->huniler->kurallar('keepchat'));
    }

    /**
     * M9d1: ülke (IP; testte ?ulke=) ve dil (Accept-Language; ?dil= önce)
     * kurala girer; sıra yine ilk tutanı seçer; yanıt dile göre değişir.
     */
    public function testUlkeVeDilKurali(): void
    {
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'ulke', ' DE ', 'fitness'));
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'dil', 'en', 'is'));
        $ingilizce = ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,tr;q=0.8'];

        self::assertSame('fitness', $this->sec(['ulke' => 'DE']));
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertSame('genel', $this->sec(['ulke' => 'FR'], ['HTTP_ACCEPT_LANGUAGE' => 'tr']));
        // Test istemcisi Accept-Language'i kendiliğinden "en-us" gönderir: Türkçe açıkça verilir.
        self::assertSame('genel', $this->sec([], ['HTTP_ACCEPT_LANGUAGE' => 'tr']), 'testte IP ülkesi yok: ülke kuralı tutmaz');
        self::assertSame('is', $this->sec([], $ingilizce));
        self::assertContains('Accept-Language', $this->client->getResponse()->getVary());
        self::assertSame('genel', $this->sec([], ['HTTP_ACCEPT_LANGUAGE' => 'tr-TR,tr;q=0.9,en;q=0.5']), 'yalnız ilk dil');
        self::assertSame('is', $this->sec(['dil' => 'en'], ['HTTP_ACCEPT_LANGUAGE' => 'tr-TR']), '?dil= başlığın önünde');
        self::assertSame('genel', $this->sec(['dil' => 'EN'], ['HTTP_ACCEPT_LANGUAGE' => 'tr-TR']), 'biçimsiz ?dil= yok sayılır');
        self::assertSame('fitness', $this->sec(['ulke' => 'DE'], $ingilizce), 'ilk tutan kural kazanır');
    }

    public function testUlkeVeDilDegeriIkiHarf(): void
    {
        self::assertSame(['ülke iki harfli kod olmalı (ör. TR, DE)'], $this->huniler->kuralEkle('keepchat', 'ulke', 'Türkiye', 'is'));
        self::assertSame(['dil iki harfli kod olmalı (ör. tr, en)'], $this->huniler->kuralEkle('keepchat', 'dil', 'eng', 'is'));
        self::assertSame([], $this->huniler->kurallar('keepchat'));
        // DB kısıtı ayrıca (tuzak §4): uygulama denetimi atlansa da tutar.
        try {
            $this->db->insert('huni_kurallari', ['app' => 'keepchat', 'sira' => 1, 'parametre' => 'ulke', 'deger' => 'türkiye', 'huni' => 'is']);
            self::fail('iki harf olmayan ülke DB\'ye girdi');
        } catch (\Doctrine\DBAL\Exception\DriverException $e) {
            self::assertStringContainsString('huni_kurallari_bolge_check', $e->getMessage());
        }
        // Adres parametresinde serbest metin sürer.
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'k', 'Türkiye', 'is'));
    }

    public function testYeniHuniKopyalarAdiDenetler(): void
    {
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'kopya', 'fitness'));
        self::assertEquals(self::tanim('fitness'), $this->huniler->taslak('keepchat', 'kopya'));
        self::assertSame(["'kopya' adında huni zaten var"], $this->huniler->yeniHuni('keepchat', 'kopya', null));
        self::assertNotSame([], $this->huniler->yeniHuni('keepchat', 'Büyük Ad', null));
        self::assertSame(["kaynak huni 'yok' yok"], $this->huniler->yeniHuni('keepchat', 'x', 'yok'));
        self::assertFalse($this->db->fetchOne("SELECT varsayilan FROM huniler WHERE ad = 'kopya'"), 'ilk olmayan huni varsayılan olmaz');
    }

    public function testOnizlemeAnahtariHuniyeBagli(): void
    {
        $anahtar = $this->huniler->onizlemeAnahtari('keepchat', 'fitness', time());

        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'fitness', 'anahtar' => $anahtar]);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'is', 'anahtar' => $anahtar]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSecimParametresiSiniri(): void
    {
        // 200 karakterden uzun değer yok sayılır (kural değerleri de en çok 200).
        $this->huniler->kuralEkle('keepchat', 'k', str_repeat('a', 200), 'fitness');

        self::assertSame('fitness', $this->sec(['k' => str_repeat('a', 200)]));
        self::assertSame('genel', $this->sec(['k' => str_repeat('a', 201)]));
    }

    /**
     * @param array<string, string> $parametreler
     * @param array<string, string> $basliklar
     */
    private function sec(array $parametreler, array $basliklar = []): ?string
    {
        $this->client->request('GET', '/huni/keepchat', $parametreler, server: $basliklar);
        self::assertResponseStatusCodeSame(200);
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($j) && \is_string($j['huni'] ?? null) ? $j['huni'] : null;
    }

    /** @return array<string, mixed> */
    private static function tanim(string $baslik): array
    {
        return [
            'sayfaBasligi' => $baslik,
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'ekran', 'baslik' => $baslik, 'bloklar' => [], 'dugme' => 'Başla'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '9,99 €', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
    }
}
