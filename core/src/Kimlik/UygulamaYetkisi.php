<?php

declare(strict_types=1);

namespace App\Kimlik;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * İstek bu uygulamanın sunucusundan mı (A5)? Anahtar sabit sürede
 * karşılaştırılır. Yapılandırılmamış (boş) anahtar HER ZAMAN reddedilir:
 * aksi hâlde boş "Bearer " başlığı hash_equals('', '') ile geçerdi.
 * BR1: geçen istek uygulamanın son istek zamanını yazar (dakikada en çok bir
 * kez) — panel "uygulamanın sunucusu bağlandı mı" bunu gösterir.
 */
final readonly class UygulamaYetkisi
{
    /** @param array<string, string> $anahtarlar uygulama → gizli anahtar */
    public function __construct(
        #[Autowire('%uygulama_anahtarlari%')]
        private array $anahtarlar,
        private Connection $db,
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

        if (!hash_equals($beklenen, substr($baslik, 7))) {
            return false;
        }
        $this->db->executeStatement(
            "INSERT INTO uygulama_istekleri (app, son_istek_at) VALUES (:app, now())
             ON CONFLICT (app) DO UPDATE SET son_istek_at = now() WHERE uygulama_istekleri.son_istek_at < now() - interval '1 minute'",
            ['app' => $app],
        );

        return true;
    }
}
