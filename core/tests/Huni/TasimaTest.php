<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use DoctrineMigrations\Version20261102120000;
use PHPUnit\Framework\TestCase;

/**
 * T1c (Ç86): tanıtım, soru ve yorum adımları ekrana taşınır. Beklenen ekranlar
 * elle yazıldı (tuzak §3) — dönüştürücünün kendisinden hesaplanmaz.
 */
final class TasimaTest extends TestCase
{
    private const G = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function testEskiTurlerEkranaTasinirGecerliKalir(): void
    {
        $eski = ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Kısa', 'dugme' => 'Başla', 'gorsel' => self::G, 'gorselMetni' => 'Kapak', 'duzen' => 'kahraman', 'hedef' => 'yorum', 'ilerlemeGoster' => false],
            ['id' => 'bos', 'tur' => 'tanitim', 'baslik' => 'Teşekkürler', 'metin' => '', 'dugme' => 'Devam'],
            ['id' => 'tek', 'tur' => 'soru', 'soru' => 'Kiminle?', 'aciklama' => 'Birini seç', 'duzen' => 'izgara', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'simge' => '👪', 'hedef' => 'yorum'], ['id' => 'b', 'metin' => 'B']]],
            ['id' => 'cok', 'tur' => 'soru', 'soru' => 'Neler?', 'secimTuru' => 'coklu', 'devamDugmesi' => 'Gönder', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]],
            ['id' => 'yazi', 'tur' => 'soru', 'soru' => 'Ne düşünüyorsun?', 'secimTuru' => 'metin', 'yanitZorunlu' => true],
            ['id' => 'yorum', 'tur' => 'yorum', 'baslik' => 'Ne diyorlar', 'metin' => 'İyi', 'yazar' => 'Ayşe', 'unvan' => '', 'dugme' => 'Devam'],
            ['id' => 'hazirlik', 'tur' => 'yukleme', 'baslik' => 'Hazırlanıyor'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]];

        $yeni = Version20261102120000::ekrana($eski);

        self::assertSame([
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'gorsel' => self::G, 'gorselMetni' => 'Kapak', 'duzen' => 'kahraman', 'bloklar' => [['tur' => 'metin', 'metin' => 'Kısa']], 'dugme' => 'Başla', 'hedef' => 'yorum', 'ilerlemeGoster' => false],
            ['id' => 'bos', 'tur' => 'ekran', 'baslik' => 'Teşekkürler', 'bloklar' => [], 'dugme' => 'Devam'],
            ['id' => 'tek', 'tur' => 'ekran', 'baslik' => 'Kiminle?', 'bloklar' => [['tur' => 'metin', 'metin' => 'Birini seç'], ['tur' => 'soru', 'duzen' => 'izgara', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'simge' => '👪', 'hedef' => 'yorum'], ['id' => 'b', 'metin' => 'B']]]]],
            ['id' => 'cok', 'tur' => 'ekran', 'baslik' => 'Neler?', 'bloklar' => [['tur' => 'soru', 'secimTuru' => 'coklu', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]]], 'dugme' => 'Gönder'],
            ['id' => 'yazi', 'tur' => 'ekran', 'baslik' => 'Ne düşünüyorsun?', 'bloklar' => [['tur' => 'soru', 'secimTuru' => 'metin', 'yanitZorunlu' => true]], 'dugme' => 'Devam'],
            ['id' => 'yorum', 'tur' => 'ekran', 'baslik' => 'Ne diyorlar', 'bloklar' => [['tur' => 'yorum', 'metin' => 'İyi', 'yazar' => 'Ayşe']], 'dugme' => 'Devam'],
            $eski['adimlar'][6],
            $eski['adimlar'][7],
        ], $yeni['adimlar']);
        self::assertSame([], Tanim::hatalar($yeni, ['monthly']));
        // Taşınmış tanım yeniden taşınınca değişmez (göç iki kez koşsa da).
        self::assertSame($yeni, Version20261102120000::ekrana($yeni));
    }
}
