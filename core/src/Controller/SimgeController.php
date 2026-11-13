<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Simge\Simgeler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * V3a: Fluent simgesi — anahtarsız, herkese açık durağan varlık. İçerik sabit
 * commit'e bağlı: değişmez → bir yıl önbellek. Maske olarak kullanılan
 * (tekrenk) görsel başka kökenden CORS ister; herkese açık olduğu için `*`.
 * SVG doğrudan açılırsa betik çalışmasın: CSP.
 */
#[Route('/simge/{stil}/{kod}.{uzanti}', name: 'huni_simge', requirements: ['stil' => 'renkli|tekrenk', 'kod' => '[0-9a-f-]{2,80}', 'uzanti' => 'webp|svg'], methods: ['GET'])]
final readonly class SimgeController
{
    public function __construct(
        private Simgeler $simgeler,
    ) {
    }

    public function __invoke(string $stil, string $kod, string $uzanti): Response
    {
        $simge = Simgeler::STILLER[$stil] === $uzanti ? $this->simgeler->getir($stil, $kod) : null;
        if (null === $simge) {
            return new Response('', 404, ['Cache-Control' => 'public, max-age=3600', 'Access-Control-Allow-Origin' => '*']);
        }

        return new Response($simge['icerik'], 200, [
            'Content-Type' => $simge['tur'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
