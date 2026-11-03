<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\TasarimYonergesi;
use PHPUnit\Framework\TestCase;

/**
 * V1/V2: yönergenin taşıması gereken kurallar — beklenti elle yazıldı
 * (tuzak §3: istem testi aynı sabitle karşılaştırdığı için içerik kaybını
 * görmüyordu, kırma W8).
 */
final class TasarimYonergesiTest extends TestCase
{
    public function testDongununVeTasarimKurallarininHepsi(): void
    {
        foreach ([
            'ekran_goruntusu', 'uyarilar', 'uyarısız olunca bitir',
            'Temayı değiştirdiysen bütün ekranlara yeniden bak',
            'Görüntüye bakmadığın ekranın nasıl göründüğünü anlatma',
            'son satırında tek kısa kelime', 'tek birincil düğme',
            'hedef sorusu ilk üç ekranda', 'e-posta', 'verilmeyen sayı',
        ] as $parca) {
            self::assertStringContainsString($parca, TasarimYonergesi::METIN);
        }
    }
}
