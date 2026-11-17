<?php

declare(strict_types=1);

namespace App\Tests\Posta;

use App\Atif\Ziyaretler;
use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Posta\EpostaSablonlari;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * E1a: müşteriye giden e-postalar panelden düzenlenen şablondan; kayıt yoksa
 * koddaki varsayılan (yine gider). Dil istekten (uygulama, abonelik sayfası)
 * ya da ödeme oturumunun hunisinden. Kayıtta bilinmeyen yer tutucu ve eksik
 * zorunlu yer tutucu reddedilir — müşteriye kodsuz / bağlantısız e-posta
 * gitmesin. Uçtan uca doğrulama kodu e-postasıyla ölçülür.
 */
final class EpostaSablonlariTest extends WebTestCase
{
    private const ANAHTAR = 'test-anahtar-keepchat';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE eposta_sablonlari, dogrulama_kodlari');
    }

    public function testVarsayilanKayitliDilVeVarsayilanaDonus(): void
    {
        $tr = $this->kodEpostasi('a@example.com');
        self::assertSame('Doğrulama kodunuz', $tr->getSubject());
        self::assertMatchesRegularExpression('/^Kodunuz: \d{6}\n\nKod 10 dakika geçerlidir\./', (string) $tr->getTextBody());
        $en = $this->kodEpostasi('b@example.com', 'en');
        self::assertSame('Your verification code', $en->getSubject());
        self::assertStringContainsString('valid for 10 minutes', (string) $en->getTextBody());

        $jeton = $this->panel();
        $this->sablonIstek('PUT', 'dogrulama_kodu', 'tr', $jeton, ['konu' => '{{uygulama}} kodu', 'metin' => "Kod: {{kod}}\r\n({{dakika}} dk)\r\n"]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/epostalar/{sablon}/{dil}', $this->client->getResponse());
        $kayit = $this->satir('dogrulama_kodu', 'tr');
        self::assertTrue($kayit['kayitli']);
        self::assertSame("Kod: {{kod}}\n({{dakika}} dk)", $kayit['metin'], 'kırpılır, satır sonu birleşir');
        self::assertFalse($this->satir('dogrulama_kodu', 'en')['kayitli'], 'diller ayrı');

        $kayitli = $this->kodEpostasi('c@example.com');
        self::assertSame('KeepChat kodu', $kayitli->getSubject());
        self::assertMatchesRegularExpression('/^Kod: \d{6}\n\(10 dk\)$/', (string) $kayitli->getTextBody());
        self::assertSame('Your verification code', $this->kodEpostasi('d@example.com', 'en')->getSubject());

        $this->sablonIstek('DELETE', 'dogrulama_kodu', 'tr', $jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('DELETE', '/panel/api/uygulamalar/{app}/epostalar/{sablon}/{dil}', $this->client->getResponse());
        $this->sablonIstek('DELETE', 'dogrulama_kodu', 'tr', $jeton);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Doğrulama kodunuz', $this->kodEpostasi('e@example.com')->getSubject());
    }

    public function testKayitDogrulamasi(): void
    {
        $jeton = $this->panel();
        $hata = function (array $govde, string $sablon = 'giris_baglantisi') use ($jeton): string {
            $this->sablonIstek('PUT', $sablon, 'tr', $jeton, $govde);
            self::assertResponseStatusCodeSame(422);
            Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/epostalar/{sablon}/{dil}', $this->client->getResponse());

            return implode(' ', array_map(static fn (mixed $h): string => \is_string($h) ? $h : '', (array) ($this->json()['hatalar'] ?? [])));
        };
        self::assertSame('bilinmeyen yer tutucu: {{kod}}', $hata(['konu' => 'x', 'metin' => '{{baglanti}} {{kod}}']));
        self::assertSame('metinde olmalı: {{baglanti}}', $hata(['konu' => '{{baglanti}}', 'metin' => 'bağlantısız']), 'zorunlu metinde aranır, konuda değil');
        self::assertStringContainsString('tek satır', $hata(['konu' => "a\nb", 'metin' => '{{baglanti}}']));
        self::assertStringContainsString('metin: 1–5000', $hata(['konu' => 'x', 'metin' => '   ']));
        self::assertSame('gövde yalnız konu ve metin alanları olan bir nesne olmalı', $hata(['konu' => 'x', 'metin' => '{{baglanti}}', 'html' => '<b>']));
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM eposta_sablonlari'));

        $this->sablonIstek('PUT', 'yok', 'tr', $jeton, ['konu' => 'x', 'metin' => 'y']);
        self::assertResponseStatusCodeSame(404);
        $this->sablonIstek('PUT', 'giris_baglantisi', 'de', $jeton, ['konu' => 'x', 'metin' => '{{baglanti}}']);
        self::assertResponseStatusCodeSame(404);
        $this->sablonIstek('PUT', 'giris_baglantisi', 'tr', '', ['konu' => 'x', 'metin' => '{{baglanti}}']);
        self::assertResponseStatusCodeSame(403);
        $sablonlar = static::getContainer()->get(EpostaSablonlari::class);
        self::assertInstanceOf(EpostaSablonlari::class, $sablonlar);
        self::assertSame([], $sablonlar->kaydet('keepchat', 'giris_baglantisi', 'tr', ['konu' => 'x', 'metin' => '{{baglanti}}']));
        $this->sablonIstek('DELETE', 'giris_baglantisi', 'tr', '');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->db->fetchOne('SELECT count(*) FROM eposta_sablonlari'), 'jetonsuz silme kaydı silmez');
        // DB kısıtı ayrıca (tuzak §4).
        $this->expectException(DriverException::class);
        $this->db->insert('eposta_sablonlari', ['app' => 'keepchat', 'sablon' => 'yok', 'dil' => 'tr', 'konu' => 'x', 'metin' => 'y']);
    }

    /** Giriş, aktivasyon ve kurtarma e-postaları müşterinin gördüğü huninin dilinde. */
    public function testOturumDiliZiyaretinHunisinden(): void
    {
        $huniler = static::getContainer()->get(Huniler::class);
        $ziyaretler = static::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertInstanceOf(Ziyaretler::class, $ziyaretler);
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari CASCADE');
        $bos = array_fill_keys(Ziyaretler::ALANLAR, null);
        foreach (['en' => 'ingilizce', 'tr' => 'turkce'] as $dil => $ad) {
            self::assertSame([], $huniler->yeniHuni('keepchat', $ad, null, 'fitness'));
            if ('en' === $dil) {
                $tanim = $huniler->taslak('keepchat', $ad);
                self::assertIsArray($tanim);
                self::assertSame([], $huniler->taslakKaydet('keepchat', $ad, ['dil' => 'en'] + $tanim));
            }
            $surum = $huniler->yayinla('keepchat', $ad);
            self::assertIsInt($surum);
            $z = $ziyaretler->kaydet('keepchat', $bos, $ad, $surum);
            $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => "cs_{$dil}", 'ziyaret_id' => $z]);
        }
        self::assertSame('en', EpostaSablonlari::oturumDili($this->db, 'cs_en'));
        self::assertSame('tr', EpostaSablonlari::oturumDili($this->db, 'cs_tr'), 'tanımda dil yoksa tr');
        self::assertSame('tr', EpostaSablonlari::oturumDili($this->db, 'cs_yok'));
    }

    /** §51: panel sayfasının doğrudan adresi; girişsiz giriş sayfasına. */
    public function testPanelSayfasiAdresiAcilir(): void
    {
        $this->client->request('GET', '/panel/epostalar');
        self::assertResponseRedirects('/panel/giris');
        $this->panel();
        $this->client->request('GET', '/panel/epostalar');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->client->getCrawler()->filter('#panel-uygulama'));
    }

    private function kodEpostasi(string $email, ?string $dil = null): Email
    {
        $this->client->request('POST', '/apps/keepchat/baglama/kod', server: ['HTTP_AUTHORIZATION' => 'Bearer '.self::ANAHTAR, 'CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(array_filter(['email' => $email, 'dil' => $dil])));
        self::assertResponseStatusCodeSame(202);
        $e = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $e);

        return $e;
    }

    private function panel(): string
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->client->request('GET', '/panel/entegrasyonlar');

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    /** @param array<mixed>|null $govde */
    private function sablonIstek(string $yontem, string $sablon, string $dil, string $jeton, ?array $govde = null): void
    {
        $this->client->request($yontem, "/panel/api/uygulamalar/keepchat/epostalar/{$sablon}/{$dil}", server: ['HTTP_X_CSRF_TOKEN' => $jeton],
            content: null === $govde ? null : (string) json_encode($govde));
    }

    /** @return array<mixed> */
    private function satir(string $sablon, string $dil): array
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/epostalar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/epostalar', $this->client->getResponse());
        foreach ((array) ($this->json()['sablonlar'] ?? []) as $s) {
            if (\is_array($s) && $sablon === $s['sablon'] && $dil === $s['dil']) {
                return $s;
            }
        }
        self::fail("{$sablon}/{$dil} yok");
    }

    /**
     * @phpstan-impure
     *
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
