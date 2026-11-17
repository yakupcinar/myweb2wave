<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Huni\Tema;
use App\Huni\Varsayilanlar;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M9c (A23): yeni hunilere kopyalanan görünüm. Kopya modeli: varsayılan
 * değişince var olan huniler değişmez. Boş huni tema + logo + paywall'ın
 * tamamını; şablondan huni tema + logo + paywall'ın uygulamaya özgü
 * alanlarını alır; kopya huni hiçbir şey almaz. Geçersiz varsayılan sessizce
 * atlanmaz.
 */
final class VarsayilanlarTest extends WebTestCase
{
    private const LOGO = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private KernelBrowser $client;
    private Connection $db;
    private Huniler $huniler;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, medya CASCADE');
        $this->gocHali();
        $this->db->executeStatement("INSERT INTO medya (app, id, genislik, yukseklik, bayt, ozgun_ad) VALUES ('keepchat', ?, 1, 1, 1, 'logo.webp')", [self::LOGO]);
        $h = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $h);
        $this->huniler = $h;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'ornek', null));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'ornek', self::ornekTanim('Pro')));
    }

    /** Sonraki testler göçteki ayar satırına güveniyor (§13). */
    protected function tearDown(): void
    {
        $this->gocHali();
        parent::tearDown();
    }

    public function testOrnekAlinirOzetSozlesmeyeUygun(): void
    {
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/huniler/ornek/ornek');
        self::assertResponseRedirects('/panel/giris');

        $this->girisYap();
        $this->ornekAl('ornek');
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/varsayilanlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/varsayilanlar', $this->client->getResponse());
        $ozet = $this->json();
        self::assertSame(['kaynak' => 'ornek', 'tema' => true, 'logo' => true, 'paywall' => true], array_diff_key($ozet, ['alindi' => 1]));
        self::assertIsString($ozet['alindi']);

        $this->ornekAl('yok');
        self::assertResponseStatusCodeSame(404);
        $this->ornekAl('ornek', '');
        self::assertResponseStatusCodeSame(403);
        $this->db->executeStatement('DELETE FROM uygulama_ayarlari');
        $this->ornekAl('ornek');
        self::assertResponseStatusCodeSame(422);
    }

    public function testBosHuniTemaLogoVePaywallinTamaminiAlir(): void
    {
        $this->varsayilanAl();
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'yeni', null));
        $t = $this->huniler->taslak('keepchat', 'yeni');
        self::assertIsArray($t);

        $ornek = self::ornekTanim('Pro');
        // §28: JSONB anahtar sırasını değiştirir — içerik karşılaştırılır.
        self::assertEquals($ornek['tema'], $t['tema']);
        self::assertSame(self::LOGO, $t['logo']);
        $adimlar = $this->adimlar('yeni');
        self::assertSame('karsilama', $adimlar[0]['id'] ?? null, 'karşılama boş taslaktan');
        self::assertEquals($ornek['adimlar'][1], end($adimlar), 'paywall adımının tamamı (id dahil)');
    }

    public function testSablondanHuniUygulamaAlanlariniAlirMetinlerSablonun(): void
    {
        $this->varsayilanAl();
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'fit', null, 'fitness'));
        $t = $this->huniler->taslak('keepchat', 'fit');
        self::assertIsArray($t);
        self::assertEquals(Tema::HAZIR_TEMALAR['gece']['tema'], $t['tema'], 'şablonun teması yerine varsayılanınki');
        self::assertSame(self::LOGO, $t['logo']);

        $ornekPw = self::ornekTanim('Pro')['adimlar'][1];
        $paywalllar = array_values(array_filter($this->adimlar('fit'), static fn (array $a): bool => 'paywall' === ($a['tur'] ?? null)));
        self::assertNotEmpty($paywalllar);
        foreach ($paywalllar as $pw) {
            foreach (Varsayilanlar::UYGULAMA_ALANLARI as $alan) {
                self::assertSame($ornekPw[$alan] ?? null, $pw[$alan] ?? null, "uygulama alanı {$alan}");
            }
            self::assertNotSame('Pro', $pw['baslik'], 'başlık şablonun');
        }
    }

    public function testKopyaHuniVarsayilanAlmaz(): void
    {
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'sade', null));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'sade', ['sayfaBasligi' => 'Sade', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Sade', 'bloklar' => [['tur' => 'metin', 'metin' => 'x']], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Sade', 'metin' => 'x', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        $this->varsayilanAl();

        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'kopya', 'sade'));
        self::assertEquals($this->huniler->taslak('keepchat', 'sade'), $this->huniler->taslak('keepchat', 'kopya'));
    }

    public function testVarsayilanDegisinceVarOlanHuniDegismezKaldirilincaBosBaslar(): void
    {
        $this->varsayilanAl();
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'ilk', null));
        $ilk = $this->huniler->taslak('keepchat', 'ilk');

        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'ornek', self::ornekTanim('Yeni başlık')));
        $this->varsayilanAl();
        self::assertEquals($ilk, $this->huniler->taslak('keepchat', 'ilk'), 'kopya modeli: eski huni dokunulmaz');
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'ikinci', null));
        $ikinci = $this->adimlar('ikinci');
        self::assertSame('Yeni başlık', end($ikinci)['baslik'] ?? null);

        $this->girisYap();
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/varsayilanlar', [], [], ['HTTP_X_CSRF_TOKEN' => $this->jeton()]);
        Sozlesme::yanitiDogrula('DELETE', '/panel/api/uygulamalar/{app}/varsayilanlar', $this->client->getResponse());
        self::assertSame(['kaynak' => null, 'alindi' => null, 'tema' => false, 'logo' => false, 'paywall' => false], $this->json());
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'bos', null));
        self::assertEquals($this->huniler->bosTaslak('keepchat'), $this->huniler->taslak('keepchat', 'bos'));
    }

    public function testGecersizVarsayilanSessizceAtlanmazHuniAcilmaz(): void
    {
        $this->varsayilanAl();
        $this->db->executeStatement("UPDATE uygulama_ayarlari SET varsayilan = jsonb_set(varsayilan, '{paywall,plan}', '\"silinmis-plan\"') WHERE app = 'keepchat'");

        $hatalar = $this->huniler->yeniHuni('keepchat', 'yeni', null);
        self::assertNotSame([], $hatalar);
        self::assertStringStartsWith('varsayılan görünüm geçersiz (Ayarlar): ', $hatalar[0]);
        self::assertNull($this->huniler->taslak('keepchat', 'yeni'));
    }

    /**
     * Şablondan açılan huni: uygulama alanında varsayılan tek otorite —
     * şablonda olup varsayılanda olmayan alan silinir (bugünkü şablonların
     * hiçbiri bu alanları taşımıyor; ileride eklenecek şablon için ölçülür).
     */
    public function testSablonunVarsayilandaOlmayanUygulamaAlaniSilinir(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'ornek', ['sayfaBasligi' => 'Ö', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'M', 'bloklar' => [['tur' => 'metin', 'metin' => 'x']], 'dugme' => 'B'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'P', 'metin' => 'm', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        $this->varsayilanAl();
        $v = static::getContainer()->get(Varsayilanlar::class);
        self::assertInstanceOf(Varsayilanlar::class, $v);
        $sablon = ['sayfaBasligi' => 'Ş', 'adimlar' => [
            ['id' => 'son', 'tur' => 'paywall', 'baslik' => 'Şablon', 'metin' => 'm', 'dugme' => 'Al', 'plan' => 'monthly', 'guvenceler' => ['guvenli-odeme']],
        ]];

        $sonuc = $v->uygula('keepchat', $sablon, true);
        self::assertIsArray($sonuc['adimlar'] ?? null);
        $adim = $sonuc['adimlar'][0] ?? null;
        self::assertIsArray($adim);
        self::assertArrayNotHasKey('guvenceler', $adim);
        self::assertSame('Şablon', $adim['baslik']);
        self::assertSame('son', $adim['id']);
    }

    /** M2c: hunide birden çok paywall olabilir; örnek SON paywall'dır (son adım). */
    public function testOrnekSonPaywalldanAlinir(): void
    {
        $tanim = self::ornekTanim('Son');
        $tanim['adimlar'] = [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'hedef' => 'erken'], ['id' => 'b', 'metin' => 'B', 'hedef' => 'paywall']]]]],
            ['id' => 'erken', 'tur' => 'paywall', 'baslik' => 'Erken', 'metin' => 'm', 'dugme' => 'Al', 'plan' => 'monthly'],
            $tanim['adimlar'][1],
        ];
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'ornek', $tanim));
        $this->varsayilanAl();

        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'yeni', null));
        $adimlar = $this->adimlar('yeni');
        self::assertSame('Son', end($adimlar)['baslik'] ?? null);
    }

    /** §4: varsayılan nesne olmalı (DB kısıtı). */
    public function testDbKisitiNesneOlmayaniReddeder(): void
    {
        $this->expectException(DriverException::class);
        $this->db->executeStatement("UPDATE uygulama_ayarlari SET varsayilan = '[]' WHERE app = 'keepchat'");
    }

    /** @return array{sayfaBasligi: string, tema: array<string, mixed>, logo: string, adimlar: list<array<string, mixed>>} */
    private static function ornekTanim(string $baslik): array
    {
        return [
            'sayfaBasligi' => 'Örnek',
            'tema' => Tema::HAZIR_TEMALAR['gece']['tema'],
            'logo' => self::LOGO,
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [['tur' => 'metin', 'metin' => 'Kısa']], 'dugme' => 'Başla'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => $baslik, 'metin' => 'Her şey dahil', 'dugme' => 'Abone ol',
                    'plan' => 'monthly', 'faydalar' => ['Reklamsız'], 'guvenceler' => ['guvenli-odeme'],
                    'sartOnayi' => true, 'sartlar' => 'https://ornek.com/sartlar'],
            ],
        ];
    }

    private function varsayilanAl(): void
    {
        $v = static::getContainer()->get(Varsayilanlar::class);
        self::assertInstanceOf(Varsayilanlar::class, $v);
        $t = $this->huniler->taslak('keepchat', 'ornek');
        self::assertIsArray($t);
        self::assertTrue($v->al('keepchat', 'ornek', $t));
    }

    private function ornekAl(string $huni, ?string $jeton = null): void
    {
        $this->client->request('POST', "/panel/api/uygulamalar/keepchat/huniler/{$huni}/ornek", [], [], ['HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton()]);
    }

    private function gocHali(): void
    {
        $this->db->executeStatement("DELETE FROM uygulama_ayarlari; INSERT INTO uygulama_ayarlari (app, ad, sema) VALUES ('keepchat', 'KeepChat', 'keepchat')");
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/huniler');
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

    /**
     * Taslağın adımları (tipli).
     *
     * @return list<array<string, mixed>>
     */
    private function adimlar(string $huni): array
    {
        $t = $this->huniler->taslak('keepchat', $huni);
        self::assertIsArray($t);
        self::assertIsArray($t['adimlar'] ?? null);
        $adimlar = [];
        foreach ($t['adimlar'] as $a) {
            self::assertIsArray($a);
            $adimlar[] = array_filter($a, \is_string(...), \ARRAY_FILTER_USE_KEY);
        }

        return $adimlar;
    }
}
