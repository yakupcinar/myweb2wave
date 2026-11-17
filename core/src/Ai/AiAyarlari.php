<?php

declare(strict_types=1);

namespace App\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** A2: AI asistanı ayarları. Anahtar boşsa AI kapalı — hiçbir istek gitmez. */
final readonly class AiAyarlari
{
    public function __construct(
        #[Autowire('%env(ANTHROPIC_API_KEY)%')]
        public string $anahtar,
        #[Autowire('%ai.model%')]
        public string $model,
        #[Autowire('%ai.efor%')]
        public string $efor,
        #[Autowire('%ai.azami_tur%')]
        public int $azamiTur,
        #[Autowire('%ai.azami_jeton%')]
        public int $azamiJeton,
    ) {
    }

    public function acik(): bool
    {
        return '' !== trim($this->anahtar);
    }
}
