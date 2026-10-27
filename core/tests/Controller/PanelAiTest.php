<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Ai\AiAyarlari;
use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\SahteClaude;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** A2: panel içi AI asistanı — Claude yerine SahteClaude (API'nin JSON biçimi). */
final class PanelAiTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya, ai_istekleri');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Merhaba')));
        SahteClaude::sifirla();
        // Saatlik sınırın deposu kalıcı: önceki testlerin sayımı taşınmasın (ölçüldü: kalan 23).
        $sinir = static::getContainer()->get('cache.rate_limiter');
        self::assertTrue(method_exists($sinir, 'clear') && $sinir->clear());
    }

    public function testGirisYapmadanKapali(): void
    {
        $this->client->request('GET', '/panel/api/ai/durum');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('POST', '/panel/api/ai/huniler/keepchat/genel/sohbet', content: '{"istem":"x"}');
        self::assertResponseRedirects('/panel/giris');
        self::assertSame([], SahteClaude::$istekler);
    }

    /** Anahtar boşsa AI kapalı: durum söyler, sohbet 503, Claude'a istek GİTMEZ. */
    public function testAnahtarYoksaKapaliIstekGitmez(): void
    {
        // Servis ilk istekte kurulur; ondan ÖNCE boş anahtarlı ayar konur.
        static::getContainer()->set(AiAyarlari::class, new AiAyarlari('', 'claude-opus-5-5', 'medium', 8, 16000));
        $this->client->disableReboot();
        $this->girisYap();
        $this->client->request('GET', '/panel/api/ai/durum');
        Sozlesme::yanitiDogrula('GET', '/panel/api/ai/durum', $this->client->getResponse());
        self::assertFalse($this->json()['acik']);
        $this->sohbet('Başlığı değiştir');

        self::assertResponseStatusCodeSame(503);
        self::assertSame([], SahteClaude::$istekler);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM ai_istekleri'));
    }

    /**
     * Ana yol: model geçersiz tanım yazar → hatalar is_error ile modele döner →
     * düzeltip kaydeder → özet. Taslak değişir, yayınlanmaz; kayıt + maliyet.
     */
    public function testDogrulamaHatasiModeleDonerDuzeltilmisTaslakKaydedilir(): void
    {
        $this->girisYap();
        $imza = $this->huniler->taslakImzasi('keepchat', 'genel');
        SahteClaude::sirala([
            ['type' => 'thinking', 'thinking' => 'hedefi kontrol et', 'signature' => 'imza-dusunce-1'],
            SahteClaude::arac('t1', 'taslak_kaydet', ['huni' => 'genel', 'imza' => $imza, 'tanim' => self::tanim('Yeni', 'olmayan')]),
        ], 'tool_use');
        SahteClaude::sirala([SahteClaude::arac('t2', 'taslak_kaydet', ['huni' => 'genel', 'imza' => $imza, 'tanim' => self::tanim('Yeni')])], 'tool_use');
        SahteClaude::sirala([SahteClaude::metin('Başlığı "Yeni" yaptım.')], 'end_turn');

        $olaylar = $this->sohbet('Sayfa başlığını Yeni yap');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/event-stream', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame(
            ['arac:taslak_kaydet', 'sonuc:hata', 'arac:taslak_kaydet', 'taslak', 'sonuc:tamam', 'metin', 'bitti:tamam'],
            array_map(static fn (array $o): string => match ($o['tur']) {
                'arac' => 'arac:'.self::metin($o['ad']),
                'arac_sonucu' => 'sonuc:'.(true === $o['hata'] ? 'hata' : 'tamam'),
                'bitti' => 'bitti:'.self::metin($o['sonuc']),
                default => self::metin($o['tur']),
            }, $olaylar),
        );
        foreach ($olaylar as $o) {
            Sozlesme::semayaGore('PanelAiOlayi', $o);
        }
        self::assertSame('Yeni', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
        self::assertNull($this->huniler->huniler('keepchat')[0]['yayinda'], 'yayınlanmadı');

        // İkinci istek: asistanın yanıtı düşünme bloğu imzasıyla AYNEN geri gider;
        // ardından araç sonucu hata + Türkçe neden.
        $mesajlar = self::yol(SahteClaude::$istekler[1], 'messages');
        self::assertSame(
            ['type' => 'thinking', 'signature' => 'imza-dusunce-1', 'thinking' => 'hedefi kontrol et'],
            self::yol($mesajlar, 1, 'content', 0),
        );
        $sonuc = self::yol($mesajlar, 2, 'content', 0);
        self::assertSame(['tool_result', 't1', true], [self::yol($sonuc, 'type'), self::yol($sonuc, 'tool_use_id'), self::yol($sonuc, 'is_error')]);
        self::assertStringContainsString('yalnız ileri', self::metin(self::yol($sonuc, 'content')));

        // Kayıt: 3 tur × (1000 girdi, 200 çıktı); maliyet elle: 3000×4 + 600×20 = 24.000 mikro$.
        self::assertSame(
            ['tur' => 3, 'girdi_jeton' => 3000, 'cikti_jeton' => 600, 'maliyet_mikrodolar' => 24000, 'sonuc' => 'tamam', 'kaydedildi' => true],
            $this->db->fetchAssociative('SELECT tur, girdi_jeton, cikti_jeton, maliyet_mikrodolar, sonuc, kaydedildi FROM ai_istekleri'),
        );
        self::assertSame(24000, end($olaylar)['maliyetMikrodolar'] ?? null);
    }

    /** SDK'nın API'ye gerçekte gönderdiği: şema anahtarları bozulmadan, önbellek, efor, yedek model, kapsam. */
    public function testIstekGovdesi(): void
    {
        $this->girisYap();
        SahteClaude::sirala([SahteClaude::metin('Tamam.')]);

        $this->sohbet('Başlığı değiştir');

        $this->client->request('GET', '/panel/api/ai/durum');
        self::assertSame(['acik' => true, 'model' => 'claude-opus-5-5'], $this->json());
        Sozlesme::yanitiDogrula('GET', '/panel/api/ai/durum', $this->client->getResponse());
        $g = SahteClaude::$istekler[0];
        self::assertSame('claude-opus-5-5', self::yol($g, 'model'));
        self::assertSame(['effort' => 'medium'], self::yol($g, 'output_config'));
        self::assertSame('default', self::yol($g, 'fallbacks'));
        self::assertStringContainsString('server-side-fallback-2026-07-01', implode("\n", SahteClaude::$basliklar[0]));
        self::assertSame(['type' => 'ephemeral'], self::yol($g, 'system', 0, 'cache_control'));
        // Açık huni kapsamı: yeni huni yok, yayınlama yok.
        $araclar = self::yol($g, 'tools');
        self::assertIsArray($araclar);
        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi'], array_column($araclar, 'name'));
        $sema = array_column($araclar, 'input_schema', 'name')['taslak_kaydet'] ?? null;
        self::assertFalse(self::yol($sema, 'additionalProperties'), 'JSON Şeması anahtarı camelCase→snake çevrilmedi');
        self::assertIsArray(self::yol($sema, '$defs', 'HuniTanimi'));
        self::assertSame(['huni', 'tanim', 'imza'], self::yol($sema, 'required'));
        // Taslak ve imza ilk mesajda (okuma turu harcanmaz); istem de orada.
        $ilk = self::metin(self::yol($g, 'messages', 0, 'content'));
        self::assertStringContainsString('Başlığı değiştir', $ilk);
        self::assertStringContainsString((string) $this->huniler->taslakImzasi('keepchat', 'genel'), $ilk);
    }

    public function testTurSiniriDonguyuKeser(): void
    {
        $this->girisYap();
        for ($i = 0; $i < 9; ++$i) {
            SahteClaude::sirala([SahteClaude::arac("t{$i}", 'huniler_listele', [])], 'tool_use');
        }

        $olaylar = $this->sohbet('Dön dur');

        self::assertCount(8, SahteClaude::$istekler);
        self::assertSame('tur_siniri', end($olaylar)['sonuc'] ?? null);
        self::assertSame(['tur' => 8, 'sonuc' => 'tur_siniri'], $this->db->fetchAssociative('SELECT tur, sonuc FROM ai_istekleri'));
    }

    public function testRetVeGecersizAnahtarAcikMesajla(): void
    {
        $this->girisYap();
        SahteClaude::sirala([], 'refusal');
        $ret = $this->sohbet('x');
        SahteClaude::hataSirala(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
        $anahtar = $this->sohbet('x');

        self::assertSame(['reddedildi', 'hata'], array_column(array_filter([...$ret, ...$anahtar], static fn (array $o): bool => 'bitti' === $o['tur']), 'sonuc'));
        self::assertStringContainsString('yanıtlamadı', self::metin(self::yol($ret, 0, 'mesaj')));
        self::assertStringContainsString('anahtarı geçersiz', self::metin(self::yol($anahtar, 0, 'mesaj')));
        self::assertSame('Merhaba', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
    }

    /** Model açık huninin dışına çıkamaz; CSRF, boş istem, saatlik sınır Claude'a gitmeden durur. */
    public function testKapsamCsrfIstemVeSinir(): void
    {
        $this->girisYap();
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'baska', null));
        SahteClaude::sirala([SahteClaude::arac('t1', 'huni_oku', ['huni' => 'baska'])], 'tool_use');
        SahteClaude::sirala([SahteClaude::metin('Yapamadım.')]);
        $olaylar = $this->sohbet('baska hunisini oku');
        self::assertContains(['tur' => 'arac_sonucu', 'ad' => 'huni_oku', 'hata' => true], $olaylar);
        self::assertStringContainsString("yalnız 'genel'", self::metin(self::yol(SahteClaude::$istekler[1], 'messages', 2, 'content', 0, 'content')));
        $istekSayisi = \count(SahteClaude::$istekler);

        $this->client->request('POST', '/panel/api/ai/huniler/keepchat/genel/sohbet', content: '{"istem":"x"}');
        self::assertResponseStatusCodeSame(403);
        $this->sohbet('   ');
        self::assertResponseStatusCodeSame(422);
        $this->sohbet('x', 'yok');
        self::assertResponseStatusCodeSame(404);
        $sinir = static::getContainer()->get('limiter.ai_sohbet');
        self::assertInstanceOf(RateLimiterFactory::class, $sinir);
        $sinir->create('yonetici')->consume(29);
        $this->sohbet('x');
        self::assertResponseStatusCodeSame(429);
        self::assertCount($istekSayisi, SahteClaude::$istekler, 'reddedilen isteklerde Claude çağrılmadı');
    }

    /** @return list<array<string, mixed>> SSE olaylarının data alanı */
    private function sohbet(string $istem, string $huni = 'genel'): array
    {
        $this->client->request('POST', "/panel/api/ai/huniler/keepchat/{$huni}/sohbet", server: ['HTTP_X_CSRF_TOKEN' => $this->jeton()], content: (string) json_encode(['istem' => $istem]));
        $olaylar = [];
        foreach (explode("\n\n", trim((string) $this->client->getInternalResponse()->getContent())) as $parca) {
            if (1 === preg_match('/^event: (\w+)\ndata: (.+)$/s', $parca, $m)) {
                /** @var array<string, mixed> $veri */
                $veri = json_decode($m[2], true, flags: \JSON_THROW_ON_ERROR);
                self::assertSame($m[1], $veri['tur'] ?? null);
                $olaylar[] = $veri;
            }
        }

        return $olaylar;
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/huniler/keepchat/genel');

        return (string) $this->client->getCrawler()->filter('#puck-duzenleyici')->attr('data-jeton');
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

    /** JSON içinde yol: her adımda dizi olduğu ölçülür. */
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
    private static function tanim(string $baslik, string $hedef = 'paywall'): array
    {
        return ['sayfaBasligi' => $baslik, 'adimlar' => [
            ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Başla.', 'dugme' => 'Başla'],
            ['id' => 'soru', 'tur' => 'soru', 'soru' => 'Hangisi?', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'hedef' => $hedef], ['id' => 'b', 'metin' => 'B']]],
            ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Plan', 'metin' => 'Abone ol.', 'dugme' => 'Abone ol'],
        ]];
    }
}
