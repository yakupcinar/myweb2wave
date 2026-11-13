<?php

declare(strict_types=1);

namespace App\Controller;

use App\Posta\Bastirma;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Postmark "Subscription Change" webhook'u → bastırma listesi (A22).
 * Postmark webhook imzalamıyor; doğrulama adrese gömülü HTTP Basic
 * (https://postmark:<şifre>@…). Şifre boşsa uç kapalı (404).
 *
 * Postmark 4xx'te yeniden denemez, 5xx'te dener: biçim dışı gövde 400
 * (düzelmez), ilgisiz kayıt türü 200 (yalnız bu tür açılır ama gelirse
 * kuyruğu tıkamasın).
 */
#[Route('/webhooks/postmark', name: 'postmark_webhook', methods: ['POST'])]
final readonly class PostmarkWebhookController
{
    public function __construct(
        private Bastirma $bastirma,
        #[Autowire('%env(POSTMARK_WEBHOOK_SIFRE)%')]
        private string $sifre,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ('' === $this->sifre) {
            return new Response('', 404);
        }
        if (!hash_equals('Basic '.base64_encode('postmark:'.$this->sifre), (string) $request->headers->get('Authorization'))) {
            return new Response('', 401);
        }
        $g = json_decode($request->getContent(), true);
        if (!\is_array($g) || !\is_string($g['RecordType'] ?? null)) {
            return new Response('', 400);
        }
        if ('SubscriptionChange' !== $g['RecordType']) {
            return new Response('', 200);
        }
        $alici = $g['Recipient'] ?? null;
        $akis = $g['MessageStream'] ?? null;
        $bastir = $g['SuppressSending'] ?? null;
        $neden = $g['SuppressionReason'] ?? null;
        $zaman = $g['ChangedAt'] ?? null;
        if (!\is_string($alici) || !str_contains($alici, '@') || !\is_string($akis) || '' === $akis
            || !\is_bool($bastir) || (null !== $neden && !\is_string($neden)) || !\is_string($zaman)) {
            return new Response('', 400);
        }
        try {
            $at = new \DateTimeImmutable($zaman);
        } catch (\Exception) {
            return new Response('', 400);
        }
        $this->bastirma->yaz($alici, $akis, $bastir, $neden, $at);

        return new Response('', 200);
    }
}
