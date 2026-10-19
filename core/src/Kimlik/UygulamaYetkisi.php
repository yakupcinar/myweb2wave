<?php

declare(strict_types=1);

namespace App\Kimlik;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * İstek bu uygulamanın sunucusundan mı (A5)? Anahtar sabit sürede
 * karşılaştırılır. Yapılandırılmamış (boş) anahtar HER ZAMAN reddedilir:
 * aksi hâlde boş "Bearer " başlığı hash_equals('', '') ile geçerdi.
 */
final readonly class UygulamaYetkisi
{
    /** @param array<string, string> $anahtarlar uygulama → gizli anahtar */
    public function __construct(
        #[Autowire('%uygulama_anahtarlari%')]
        private array $anahtarlar,
    ) {
    }

    public function izinli(Request $request, string $app): bool
    {
        $beklenen = $this->anahtarlar[$app] ?? '';
        if ('' === $beklenen) {
            return false;
        }
        $baslik = (string) $request->headers->get('Authorization');
        if (!str_starts_with($baslik, 'Bearer ')) {
            return false;
        }

        return hash_equals($beklenen, substr($baslik, 7));
    }
}
