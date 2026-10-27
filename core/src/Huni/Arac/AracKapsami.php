<?php

declare(strict_types=1);

namespace App\Huni\Arac;

/**
 * Araçların dokunabileceği yer (A0): uygulama hep sabit (yapı gereği başka
 * uygulamaya erişilemez — araç girdisinde uygulama alanı yok); huni verilmişse
 * yalnız o huni (panelde açık huni üzerindeki sohbet).
 */
final readonly class AracKapsami
{
    public function __construct(
        public string $app,
        public ?string $huni = null,
    ) {
    }
}
