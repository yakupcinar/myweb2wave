<?php

declare(strict_types=1);

namespace App\Tests\Ozellik;

use App\Huni\Huniler;
use App\Ozellik\Ozellikler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * T2b: sunucu ve oynatıcı aynı olaylardan aynı özellikleri üretir. Örnekler
 * ortak dosyada (huni-bilesenleri/ozellik-ornekleri.json); oynatıcı tarafı
 * ozellikler.test.ts. Burada olaylar gerçek uçlardan geçer (oynatıcı gibi).
 */
final class OzellikEsligiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'kiminle', 'tur' => 'ekran', 'baslik' => 'Kiminle?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'aile', 'metin' => 'A'], ['id' => 'is', 'metin' => 'B']]]]],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));
        // M4a: örneklerdeki deney ataması aktif bir içerik deneyine düşer.
        $db->executeStatement('TRUNCATE deneyler');
        $db->executeStatement("INSERT INTO deneyler (app, ad, tur, huni, varyantlar, durum) VALUES ('keepchat', 'baslik', 'icerik', 'genel', '[{\"ad\":\"a\",\"pay\":50},{\"ad\":\"b\",\"pay\":50}]', 'aktif')");
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function ornekler(): iterable
    {
        $dosya = \dirname(__DIR__, 3).'/huni-bilesenleri/ozellik-ornekleri.json';
        $veri = json_decode((string) file_get_contents($dosya), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($veri) && \is_array($veri['ornekler']));
        foreach ($veri['ornekler'] as $o) {
            \assert(\is_array($o) && \is_string($o['ad']));
            yield $o['ad'] => [$o];
        }
    }

    /** @param array<mixed> $ornek */
    #[\PHPUnit\Framework\Attributes\DataProvider('ornekler')]
    public function testSunucuOrnektekiOzellikleriUretir(array $ornek): void
    {
        // Oynatıcının ziyaret gövdesi (lib/ziyaret.ts): adresteki alanlar, izin.
        self::assertIsString($ornek['adres']);
        parse_str(ltrim($ornek['adres'], '?'), $adres);
        $govde = ['app' => 'keepchat', 'huni' => $ornek['huni'], 'surum' => $ornek['surum']];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $ad) {
            if (\is_string($adres[$ad] ?? null) && '' !== $adres[$ad]) {
                $govde[$ad] = $adres[$ad];
            }
        }
        if ('evet' === $ornek['izin']) {
            $govde += array_intersect_key($adres, ['fbclid' => 1, 'ttclid' => 1]) + ['izin' => true];
        } elseif ('hayir' === $ornek['izin']) {
            $govde['izin'] = false;
        }
        if (isset($ornek['deneyler'])) {
            $govde['deneyler'] = $ornek['deneyler'];
        }
        $this->client->jsonRequest('POST', '/huni/ziyaretler', $govde);
        self::assertResponseStatusCodeSame(201);
        $yanit = json_decode((string) $this->client->getResponse()->getContent(), true);
        \assert(\is_array($yanit) && \is_string($yanit['ziyaret']));
        foreach ((array) $ornek['olaylar'] as $olay) {
            \assert(\is_array($olay));
            $this->client->jsonRequest('POST', "/huni/ziyaretler/{$yanit['ziyaret']}/ilerleme", $olay);
            self::assertResponseStatusCodeSame(204);
        }

        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $gercek = (new Ozellikler($db))->ziyaretin($yanit['ziyaret']);
        $beklenen = (array) $ornek['beklenen'];
        ksort($beklenen);
        ksort($gercek);
        self::assertSame($beklenen, $gercek);
    }
}
