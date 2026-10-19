<?php

declare(strict_types=1);

namespace App\Billing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Stripe\Event;

/**
 * Stripe olaylarını abonelik kaydına işler.
 *
 * - Tekrar (R3): olay kimliği aynı işlemde processed_webhook_events'e
 *   yazılır; ikinci kopya PK kısıtında düşer, hiçbir şey uygulanmaz.
 * - Sıra: Stripe sırayı garanti etmiyor (tuzak §13). Durumu yalnız abonelik
 *   olayları yazar ve daha eski olay daha yeniyi ezmez (status_event_created).
 * - checkout.session.completed durum YAZMAZ, yalnız e-postayı bağlar.
 */
final readonly class SubscriptionSync
{
    public function __construct(private Connection $db)
    {
    }

    public function apply(Event $event): void
    {
        try {
            $this->db->transactional(function (Connection $db) use ($event): void {
                $db->insert('processed_webhook_events', ['event_id' => $event->id, 'type' => $event->type]);

                $nesne = $event->data->object->toArray();
                match ($event->type) {
                    'checkout.session.completed' => $this->linkEmail($db, $nesne),
                    'customer.subscription.created',
                    'customer.subscription.updated',
                    'customer.subscription.deleted' => $this->applyStatus($db, $nesne, $event->created),
                    default => null,
                };
            });
        } catch (UniqueConstraintViolationException) {
            // Bu olay daha önce işlendi; Stripe yeniden göndermesin diye 200.
        }
    }

    /** @param array<mixed> $session */
    private function linkEmail(Connection $db, array $session): void
    {
        $sub = $session['subscription'] ?? null;
        $cus = $session['customer'] ?? null;
        if (($session['mode'] ?? null) !== 'subscription' || !\is_string($sub) || !\is_string($cus)) {
            return;
        }
        $detay = $session['customer_details'] ?? null;
        $email = \is_array($detay) && \is_string($detay['email'] ?? null) ? $detay['email'] : null;

        $db->executeStatement(
            'INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, email)
             VALUES (:sub, :cus, :email)
             ON CONFLICT (stripe_subscription_id) DO UPDATE SET email = EXCLUDED.email, updated_at = now()',
            ['sub' => $sub, 'cus' => $cus, 'email' => $email],
        );
    }

    /** @param array<mixed> $sub */
    private function applyStatus(Connection $db, array $sub, int $eventCreated): void
    {
        $id = $sub['id'] ?? null;
        $cus = $sub['customer'] ?? null;
        $status = $sub['status'] ?? null;
        if (!\is_string($id) || !\is_string($cus) || !\is_string($status)) {
            return;
        }

        $db->executeStatement(
            'INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, status_event_created)
             VALUES (:id, :cus, :status, :created)
             ON CONFLICT (stripe_subscription_id) DO UPDATE SET
                 stripe_customer_id = EXCLUDED.stripe_customer_id,
                 status = EXCLUDED.status,
                 status_event_created = EXCLUDED.status_event_created,
                 updated_at = now()
             WHERE subscriptions.status_event_created IS NULL
                OR subscriptions.status_event_created <= EXCLUDED.status_event_created',
            ['id' => $id, 'cus' => $cus, 'status' => $status, 'created' => $eventCreated],
        );
    }
}
