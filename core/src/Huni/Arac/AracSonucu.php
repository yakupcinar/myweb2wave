<?php

declare(strict_types=1);

namespace App\Huni\Arac;

/** Bir araç çağrısının sonucu; `hata` modele is_error olarak gider (A0). */
final readonly class AracSonucu
{
    /** @param array<string, mixed> $icerik */
    public function __construct(
        public array $icerik,
        public bool $hata = false,
    ) {
    }

    public static function hata(string ...$hatalar): self
    {
        return new self(['hatalar' => array_values($hatalar)], true);
    }
}
