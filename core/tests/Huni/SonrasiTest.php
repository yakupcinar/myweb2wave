<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use PHPUnit\Framework\TestCase;

/**
 * M3b (A16): ödeme sonrası ekran tanımın kökünde (adım değil) — başlık +
 * bloklar (+ giriş düğmesi metni); o sayfada cevap yok: soru bloğu ve
 * koşullu değer olmaz; blok görselleri de "bu uygulamada var mı" denetimine.
 */
final class SonrasiTest extends TestCase
{
    private const PLANLAR = ['monthly'];
    private const G = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function testGecerli(): void
    {
        self::assertSame([], Tanim::hatalar(self::tanim(['baslik' => 'Hoş geldin', 'bloklar' => [['tur' => 'metin', 'metin' => 'Uygulamayı aç.']]]), self::PLANLAR));
        self::assertSame([], Tanim::hatalar(self::tanim(['baslik' => 'Hoş geldin', 'bloklar' => [], 'dugme' => 'Uygulamaya geç']), self::PLANLAR));
    }

    public function testGecersizler(): void
    {
        foreach ([
            [['baslik' => 'X'], 'alanlar tam olarak baslik, bloklar'],
            [['baslik' => 'X', 'bloklar' => [], 'hedef' => 'giris'], 'alanlar tam olarak'],
            [['baslik' => '', 'bloklar' => []], 'sonrasi baslik'],
            [['baslik' => 'X', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]]]], 'soru bloğu olmaz'],
            [['baslik' => 'X', 'bloklar' => [['tur' => 'metin', 'metin' => 'M', 'degerler' => [['kosullar' => [['ozellik' => 'cevap.giris', 'islem' => 'var']], 'alan' => 'metin', 'deger' => 'Y']]]]], 'koşullu değer olmaz'],
            [['baslik' => 'X', 'bloklar' => [['tur' => 'html', 'metin' => 'M']]], 'tür baslik, metin'],
        ] as [$sonrasi, $hata]) {
            self::assertStringContainsString($hata, implode(' | ', Tanim::hatalar(self::tanim($sonrasi), self::PLANLAR)), (string) json_encode($sonrasi));
        }
    }

    public function testGorselleriSayilir(): void
    {
        self::assertContains(self::G, Tanim::gorseller(self::tanim(['baslik' => 'X', 'bloklar' => [['tur' => 'gorsel', 'gorsel' => self::G]]])));
    }

    /** @return array<string, mixed> */
    private static function tanim(mixed $sonrasi): array
    {
        return ['sayfaBasligi' => 'X', 'sonrasi' => $sonrasi, 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]];
    }
}
