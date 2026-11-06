<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use PHPUnit\Framework\TestCase;

/**
 * M2c (A15): cevaba göre farklı paywall = hunide birden çok paywall adımı.
 * Karar: "tam bir kez" kalktı, SON adım paywall olmalı (oynatıcının gösterim
 * koşuluyla atlaması bir yerde dursun); öndeki paywall'a seçenek hedefiyle ya
 * da koşullu yönlendirmeyle gidilir.
 */
final class CokPaywallTest extends TestCase
{
    private const PLANLAR = ['monthly', 'yearly'];

    public function testSecenekHedefiVeYonlendirmeyleIkiPaywallGecerli(): void
    {
        self::assertSame([], Tanim::hatalar(self::tanim(), self::PLANLAR));
        $yonlendirmeli = self::tanim(secenekHedefi: false);
        $yonlendirmeli['adimlar'][1]['yonlendirme'] = [['kosullar' => [['ozellik' => 'cevap.hedef', 'islem' => 'icerir', 'deger' => 'kas']], 'hedef' => 'pw-yillik']];
        self::assertSame([], Tanim::hatalar($yonlendirmeli, self::PLANLAR));
    }

    public function testSonAdimPaywallOlmali(): void
    {
        $t = self::tanim();
        // Son paywall'ın yerine ekran: öndeki paywall yetmez.
        $t['adimlar'][3] = ['id' => 'son', 'tur' => 'ekran', 'baslik' => 'Bitti', 'bloklar' => [], 'dugme' => 'Tamam'];
        self::assertContains("adım 4: son adım paywall olmalı (başka paywall'lar öne konup yönlendirmeyle açılabilir)", Tanim::hatalar($t, self::PLANLAR));
    }

    /**
     * @return array{sayfaBasligi: string, adimlar: list<array<string, mixed>>}
     */
    private static function tanim(bool $secenekHedefi = true): array
    {
        return ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'hedef', 'tur' => 'ekran', 'baslik' => 'Hedefin?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [
                ['id' => 'kas', 'metin' => 'Kas'] + ($secenekHedefi ? ['hedef' => 'pw-yillik'] : []),
                ['id' => 'kilo', 'metin' => 'Kilo'],
            ]]]] + ($secenekHedefi ? [] : []),
            ['id' => 'pw-aylik', 'tur' => 'paywall', 'baslik' => 'Aylık', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
            ['id' => 'pw-yillik', 'tur' => 'paywall', 'baslik' => 'Yıllık', 'metin' => '', 'dugme' => 'Al', 'plan' => 'yearly'],
        ]];
    }
}
