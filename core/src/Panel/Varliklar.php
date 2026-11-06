<?php

declare(strict_types=1);

namespace App\Panel;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Panelin derlenmiş betik/stil adresleri (B5). Vite manifestinden — içerik
 * özetli adlar (tuzak §30). Derlenmemişse null: çağıran sessizce boş sayfa
 * değil, açık hata döner.
 */
final readonly class Varliklar
{
    public const KOK = '/varliklar/panel/';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/varliklar/panel/.vite/manifest.json')]
        private string $manifest,
    ) {
    }

    /** @return array{betik: string, stiller: list<string>}|null */
    public function giris(string $kaynak): ?array
    {
        $manifest = is_file($this->manifest) ? json_decode((string) file_get_contents($this->manifest), true) : null;
        $giris = \is_array($manifest) && \is_array($manifest[$kaynak] ?? null) ? $manifest[$kaynak] : null;
        if (null === $giris || !\is_string($giris['file'] ?? null)) {
            return null;
        }
        $stiller = [];
        foreach (\is_array($giris['css'] ?? null) ? $giris['css'] : [] as $c) {
            if (\is_string($c)) {
                $stiller[] = self::KOK.$c;
            }
        }

        return ['betik' => self::KOK.$giris['file'], 'stiller' => $stiller];
    }
}
