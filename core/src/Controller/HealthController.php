<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Çalışan sistemin sürümü ve DB'ye ulaşıp ulaşmadığı. DB gerçekten
 * sorgulanır: sabit "ok" dönen uç kendini raporlar, sistemi değil (tuzak §11).
 */
#[Route('/health', name: 'health', methods: ['GET'])]
final readonly class HealthController
{
    public function __construct(
        private Connection $db,
        #[Autowire('%env(default::APP_VERSION)%')]
        private ?string $version,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            $this->db->executeQuery('SELECT 1')->fetchOne();
            $dbOk = true;
        } catch (DbalException) {
            $dbOk = false;
        }

        return new JsonResponse([
            'status' => $dbOk ? 'ok' : 'degraded',
            'db' => $dbOk ? 'ok' : 'down',
            'version' => $this->version ?? 'unknown',
        ], 200);
    }
}
