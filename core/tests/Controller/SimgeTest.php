<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Simge\Simgeler;
use App\Tests\Support\SahteSimge;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * V3a: Fluent simgesi — sabit commit'ten bir kez alınır, küçültülür,
 * önbellekten sunulur; alınamayan/bilinmeyen/güvensiz olan 404 (huni cihaz
 * emojisine döner).
 */
final class SimgeTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $onbellek;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $dizin = static::getContainer()->getParameter('kernel.cache_dir');
        self::assertIsString($dizin);
        $this->onbellek = $dizin.'/simge';
        (new Filesystem())->remove($this->onbellek);
        SahteSimge::sifirla();
    }

    public function testRenkliSimgeKucultulurWebpOlarakOnbellektenSunulur(): void
    {
        SahteSimge::$govde = self::png(256);

        $this->client->request('GET', '/simge/renkli/1f319.webp');

        self::assertResponseIsSuccessful();
        $yanit = $this->client->getResponse();
        self::assertSame('image/webp', $yanit->headers->get('Content-Type'));
        self::assertStringContainsString('immutable', (string) $yanit->headers->get('Cache-Control'));
        self::assertSame('*', $yanit->headers->get('Access-Control-Allow-Origin'));
        $boyut = getimagesizefromstring((string) $yanit->getContent());
        self::assertIsArray($boyut);
        self::assertSame([128, 128, \IMAGETYPE_WEBP], [$boyut[0], $boyut[1], $boyut[2]]);
        // Sabit commit + dizindeki yol (boşluk kodlu).
        $dizin = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/config/huni/fluent-simgeler.json'), true);
        self::assertIsArray($dizin);
        self::assertIsString($dizin['sha']);
        self::assertSame(['https://raw.githubusercontent.com/microsoft/fluentui-emoji/'.$dizin['sha'].'/assets/Crescent%20moon/3D/crescent_moon_3d.png'], SahteSimge::$adresler);

        $this->client->request('GET', '/simge/renkli/1f319.webp');
        self::assertResponseIsSuccessful();
        self::assertSame(1, SahteSimge::sayi(), 'ikinci istek önbellekten');
    }

    public function testTekRenkSvgCspIleSunulur(): void
    {
        SahteSimge::$govde = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M0 0h32v32z" fill="#212121"/></svg>';

        $this->client->request('GET', '/simge/tekrenk/1f319.svg');

        self::assertResponseIsSuccessful();
        self::assertSame('image/svg+xml', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString("default-src 'none'", (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
    }

    /** Sabit commit'ten gelse de betik/olay/dış bağlantı taşıyan SVG sunulmaz, önbelleğe de yazılmaz. */
    #[\PHPUnit\Framework\Attributes\TestWith(['<svg><script>alert(1)</script></svg>'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['<svg onload="alert(1)"></svg>'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['<svg><image href="https://kotu.example/x.png"/></svg>'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['<svg><foreignObject></foreignObject></svg>'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['<html><svg></svg></html>'])]
    public function testGuvensizSvgReddedilir(string $svg): void
    {
        SahteSimge::$govde = $svg;

        $this->client->request('GET', '/simge/tekrenk/1f319.svg');

        self::assertResponseStatusCodeSame(404);
        self::assertFileDoesNotExist($this->onbellek.'/tekrenk/1f319.svg');
    }

    public function testDizindeOlmayanBicimiYanlisYaDaBozukIstekGitHubaGitmez(): void
    {
        foreach (['/simge/renkli/1f1f9-1f1f7.webp', '/simge/renkli/1f319.svg', '/simge/tekrenk/1f319.webp', '/simge/gri/1f319.webp', '/simge/renkli/..%2Fx.webp'] as $adres) {
            $this->client->request('GET', $adres);
            self::assertResponseStatusCodeSame(404, $adres);
        }
        self::assertSame([], SahteSimge::$adresler);
    }

    public function testAgHatasiYaDaBozukGorsel404VeOnbellegeYazilmaz(): void
    {
        SahteSimge::$agHatasi = 'bağlantı kurulamadı';
        $this->client->request('GET', '/simge/renkli/1f319.webp');
        self::assertResponseStatusCodeSame(404);

        SahteSimge::$agHatasi = null;
        SahteSimge::$durum = 500;
        $this->client->request('GET', '/simge/renkli/1f319.webp');
        self::assertResponseStatusCodeSame(404);

        SahteSimge::$durum = 200;
        SahteSimge::$govde = 'png değil';
        $this->client->request('GET', '/simge/renkli/1f319.webp');
        self::assertResponseStatusCodeSame(404);
        self::assertFileDoesNotExist($this->onbellek.'/renkli/1f319.webp');
        self::assertCount(3, SahteSimge::$adresler, 'hata önbelleğe yazılmadı, her istek yeniden denedi');
    }

    public function testKodKuraliHuniBilesenleriyleOrtak(): void
    {
        $ornekler = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/huni-bilesenleri/simge-ornekleri.json'), true);
        self::assertIsArray($ornekler);
        self::assertIsArray($ornekler['ornekler']);
        self::assertCount(6, $ornekler['ornekler']);
        foreach ($ornekler['ornekler'] as $o) {
            self::assertIsArray($o);
            self::assertIsString($o['emoji']);
            self::assertSame($o['kod'], Simgeler::kod($o['emoji']));
        }
    }

    /** @param positive-int $kenar */
    private static function png(int $kenar): string
    {
        $g = imagecreatetruecolor($kenar, $kenar);
        imagefill($g, 0, 0, (int) imagecolorallocate($g, 250, 200, 60));
        ob_start();
        imagepng($g);

        return (string) ob_get_clean();
    }
}
