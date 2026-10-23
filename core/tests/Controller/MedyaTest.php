<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Huni görselleri (E2.3): güvenilmeyen dosya → doğrulanmış WebP. */
final class MedyaTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private string $dizin;
    private string $gecici;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE medya, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari');
        // .env.test: MEDYA_DIZINI=%kernel.project_dir%/var/test-medya (resolve: ile çözülmeli).
        $this->dizin = \dirname(__DIR__, 2).'/var/test-medya';
        foreach (glob($this->dizin.'/*') ?: [] as $f) {
            unlink($f);
        }
        $this->gecici = sys_get_temp_dir().'/medya-test-'.bin2hex(random_bytes(4));
        mkdir($this->gecici);
    }

    public function testPngYuklenirWebpOlarakSaklanirIcerikOzetiKimlik(): void
    {
        $this->girisYap();

        $id = $this->yukle($this->resim('png', 300, 200));

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $id);
        $dosya = $this->dizin.'/'.$id.'.webp';
        self::assertFileExists($dosya, 'dizin %kernel.project_dir% çözülerek kurulmalı');
        $icerik = (string) file_get_contents($dosya);
        self::assertSame('RIFF', substr($icerik, 0, 4));
        self::assertSame('WEBP', substr($icerik, 8, 4));
        self::assertSame($id, hash('sha256', $icerik));
        self::assertEquals([300, 200], $this->db->fetchNumeric('SELECT genislik, yukseklik FROM medya'));
    }

    public function testBuyukGorselKucultulurAyniGorselTekKopya(): void
    {
        $this->girisYap();
        $yol = $this->resim('jpg', 2400, 1200);

        $a = $this->yukle($yol);
        $b = $this->yukle($yol);

        self::assertSame($a, $b);
        self::assertSame(1, $this->sayim('SELECT count(*) FROM medya'));
        $boyut = getimagesize($this->dizin.'/'.$a.'.webp');
        self::assertIsArray($boyut);
        self::assertSame([1600, 800], [$boyut[0], $boyut[1]]);
    }

    public function testExifVeSonaEklenmisBetikYenidenKodlamadaKalmaz(): void
    {
        $this->girisYap();
        $jpg = (string) file_get_contents($this->resim('jpg', 50, 50));
        // SOI'den hemen sonra EXIF (APP1) segmenti + dosya sonuna betik (poliglot).
        $exif = "Exif\0\0GIZLI-KONUM-41.0082N";
        $kirli = substr($jpg, 0, 2)."\xFF\xE1".pack('n', \strlen($exif) + 2).$exif.substr($jpg, 2).'<script>alert(1)</script>';
        $yol = $this->gecici.'/kirli.jpg';
        file_put_contents($yol, $kirli);
        self::assertNotFalse(getimagesize($yol), 'kirli dosya yine de geçerli JPEG olmalı');

        $id = $this->yukle($yol);

        $cikti = (string) file_get_contents($this->dizin.'/'.$id.'.webp');
        self::assertStringNotContainsString('GIZLI-KONUM', $cikti);
        self::assertStringNotContainsString('<script>', $cikti);
    }

    /** @return iterable<string, array{string, string}> */
    public static function reddedilenler(): iterable
    {
        yield 'svg' => ['svg', 'yalnız PNG, JPEG ya da WebP'];
        yield 'gif' => ['gif', 'yalnız PNG, JPEG ya da WebP'];
        yield 'uzantısı png metin' => ['sahte', 'yalnız PNG, JPEG ya da WebP'];
        yield '5 MB üstü' => ['iri', '5 MB'];
        yield 'dev kenar' => ['uzun', 'kenarlı'];
        yield 'çok piksel' => ['genis', 'megapiksel'];
        yield 'PHP sınırını aşan 7 MB' => ['php-siniri', '5 MB'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reddedilenler')]
    public function testGuvenilmeyenDosyaReddedilir(string $tur, string $beklenen): void
    {
        $this->girisYap();
        $yol = match ($tur) {
            'svg' => $this->yaz('a.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
            'gif' => $this->resim('gif', 10, 10),
            'sahte' => $this->yaz('a.png', 'merhaba, ben resim değilim'),
            'iri' => $this->yaz('iri.png', random_bytes(5 * 1024 * 1024 + 1)),
            'php-siniri' => $this->yaz('dev.png', random_bytes(7 * 1024 * 1024)),
            // Yalnız başlık: getimagesize IHDR'yi okur, piksel üretmek gerekmez (bellek).
            'genis' => $this->yaz('genis.png', self::pngBasligi(4001, 4000)),
            default => $this->resim('png', 9000, 10),
        };

        $this->yukle($yol, 422);

        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($j);
        self::assertIsArray($j['hatalar'] ?? null);
        self::assertStringContainsString($beklenen, implode(' ', array_filter($j['hatalar'], \is_string(...))));
        self::assertSame(0, $this->sayim('SELECT count(*) FROM medya'));
        self::assertSame([], glob($this->dizin.'/*.webp') ?: []);
    }

    public function testYuklemeGirisVeCsrfIster(): void
    {
        $yol = $this->resim('png', 10, 10);
        $this->client->request('POST', '/panel/medya/keepchat', [], ['dosya' => new UploadedFile($yol, 'a.png', null, null, true)]);
        self::assertResponseRedirects('/panel/giris');

        $this->girisYap();
        $this->client->request('POST', '/panel/medya/keepchat', [], ['dosya' => new UploadedFile($yol, 'a.png', null, null, true)], ['HTTP_X_CSRF_TOKEN' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->sayim('SELECT count(*) FROM medya'));
    }

    public function testSunumUstbilgileriVeBilinmeyenGorsel404(): void
    {
        $this->girisYap();
        $id = $this->yukle($this->resim('png', 20, 20));

        $this->client->request('GET', "/medya/{$id}.webp");
        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('Cache-Control', 'immutable, max-age=31536000, public');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $this->client->request('GET', '/medya/'.str_repeat('a', 64).'.webp');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/medya/..%2F..%2F.env.webp');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTanimdakiGorselBuUygulamayaYuklenmisOlmali(): void
    {
        $this->girisYap();
        $id = $this->yukle($this->resim('png', 20, 20));
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $huniler->yeniHuni('keepchat', 'genel', null);

        $tanim = static fn (string $g): array => ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'a', 'tur' => 'tanitim', 'baslik' => 'A', 'metin' => '', 'dugme' => 'B', 'gorsel' => $g],
            ['id' => 's', 'tur' => 'soru', 'soru' => 'S', 'secenekler' => [['id' => 'x', 'metin' => 'X', 'gorsel' => $g], ['id' => 'y', 'metin' => 'Y']]],
            ['id' => 'p', 'tur' => 'paywall', 'baslik' => 'P', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]];

        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', $tanim($id)));
        self::assertStringContainsString('bu uygulamada yok', implode('', $huniler->taslakKaydet('keepchat', 'genel', $tanim(str_repeat('b', 64)))));
        self::assertStringContainsString('görsel kimliği geçersiz', implode('', $huniler->taslakKaydet('keepchat', 'genel', $tanim('../../x'))));
        $this->db->executeStatement("UPDATE medya SET app = 'baska'");
        self::assertStringContainsString('bu uygulamada yok', implode('', $huniler->taslakKaydet('keepchat', 'genel', $tanim($id))), 'başka uygulamanın görseli');
    }

    private function yukle(string $yol, int $beklenen = 201): string
    {
        $this->client->request('GET', '/panel/huniler');
        $jeton = (string) $this->client->getCrawler()->filter('[data-uygulama=keepchat] input[name=_token]')->first()->attr('value');
        $this->client->request('POST', '/panel/medya/keepchat', [], [
            'dosya' => new UploadedFile($yol, basename($yol), null, null, true),
        ], ['HTTP_X_CSRF_TOKEN' => $jeton]);
        self::assertResponseStatusCodeSame($beklenen);
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($j) && \is_string($j['id'] ?? null) ? $j['id'] : '';
    }

    /**
     * @param int<1, max> $g
     * @param int<1, max> $y
     */
    private function resim(string $tur, int $g, int $y): string
    {
        $r = imagecreatetruecolor($g, $y);
        self::assertNotFalse($r);
        imagefilledrectangle($r, 0, 0, intdiv($g, 2), $y - 1, (int) imagecolorallocate($r, 22, 163, 74));
        $yol = $this->gecici."/r{$g}x{$y}.{$tur}";
        match ($tur) {
            'png' => imagepng($r, $yol),
            'jpg' => imagejpeg($r, $yol, 90),
            default => imagegif($r, $yol),
        };

        return $yol;
    }

    /** Piksel verisi olmayan PNG: imza + IHDR (CRC'li). */
    private static function pngBasligi(int $g, int $y): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $g, $y, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
    }

    private function yaz(string $ad, string $icerik): string
    {
        file_put_contents($this->gecici.'/'.$ad, $icerik);

        return $this->gecici.'/'.$ad;
    }

    private function sayim(string $sql): int
    {
        $n = $this->db->fetchOne($sql);

        return is_numeric($n) ? (int) $n : -1;
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
