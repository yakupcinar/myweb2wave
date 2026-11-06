<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mcp\ApiAnahtarlari;
use App\Mcp\McpSunucusu;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP uç noktası (A5a, B6): Streamable HTTP, `Authorization: Bearer
 * <kişisel API anahtarı>`. Panel güvenlik duvarının DIŞINDA (kendi kimliği).
 * Anahtar sorgu dizgesinde kabul edilmez (günlüklere sızar; MCP spec).
 * Host izni ortamdan (DNS rebinding koruması; varsayılan yalnız yerel).
 */
final class McpController
{
    public function __construct(
        private readonly ApiAnahtarlari $anahtarlar,
        private readonly McpSunucusu $sunucu,
        /** @var list<string> */
        #[Autowire('%env(csv:MCP_IZINLI_HOSTLAR)%')]
        private readonly array $izinliHostlar,
    ) {
    }

    #[Route('/mcp', name: 'mcp', methods: ['GET', 'POST', 'DELETE'])]
    public function __invoke(Request $request, RateLimiterFactory $mcpLimiter): Response
    {
        $baslik = $request->headers->get('Authorization', '');
        $sahip = str_starts_with($baslik, 'Bearer ') ? $this->anahtarlar->dogrula(substr($baslik, 7)) : null;
        if (null === $sahip) {
            return new JsonResponse(
                ['error' => 'invalid_token', 'error_description' => 'Geçerli bir kişisel API anahtarı gerekli: Authorization: Bearer mw2w_…'],
                401,
                ['WWW-Authenticate' => 'Bearer realm="MyWeb2Wave MCP"', 'Cache-Control' => 'no-store'],
            );
        }
        if (!$mcpLimiter->create((string) $sahip['id'])->consume()->isAccepted()) {
            return new JsonResponse(['error' => 'too_many_requests'], 429, ['Retry-After' => '60']);
        }

        $f = new Psr17Factory();
        $transport = new StreamableHttpTransport(
            (new PsrHttpFactory($f, $f, $f, $f))->createRequest($request),
            $f,
            $f,
            middleware: [new CorsMiddleware(), new DnsRebindingProtectionMiddleware($this->izinliHostlar)],
        );
        $yanit = (new HttpFoundationFactory())->createResponse($this->sunucu->kur($sahip['app'], $sahip['id'])->run($transport), true);
        $yanit->headers->set('Cache-Control', 'no-store');

        return $yanit;
    }
}
