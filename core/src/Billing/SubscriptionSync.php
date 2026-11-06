<?php

declare(strict_types=1);

namespace App\Billing;

use App\Atif\DonusumAktarimi;
use App\RevenueCat\Aktarim;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Stripe\Event;

/**
 * Stripe olaylarını abonelik kaydına işler.
 *
 * - Tekrar (R3): olay kimliği aynı işlemde processed_webhook_events'e
 *   yazılır; ikinci kopya PK kısıtında düşer, hiçbir şey uygulanmaz.
 * - Sıra: Stripe sırayı garanti etmiyor (tuzak §13). Durumu yalnız abonelik
 *   olayları yazar ve daha eski olay daha yeniyi ezmez (status_event_created).
 * - checkout.session.completed durum YAZMAZ, yalnız e-postayı bağlar ve
 *   huni ziyaretinden açılan oturumu ödendi işaretler (A6).
 * - Uygulama (A4) nesnenin metadata.app'inden; yoksa olay işlendi sayılır
 *   ama UYGULANMAZ — uygulamasız abonelik hiçbir uygulamayı açmasın.
 * - Hak kaynağı RevenueCat (A9): abonelik bağlı bir kişininse aynı işlemde
 *   RevenueCat giden kutusuna yazılır; gönderim işlemden sonra (webhook ucu).
 */
final readonly class SubscriptionSync
{
    public function __construct(
        private Connection $db,
        private LoggerInterface $logger,
        private Aktarim $revenuecat,
        private DonusumAktarimi $donusum,
    ) {
    }

    public function apply(Event $event): void
    {
        try {
            $this->db->transactional(function (Connection $db) use ($event): void {
                $db->insert('processed_webhook_events', ['event_id' => $event->id, 'type' => $event->type]);

                $nesne = $event->data->object->toArray();
                /** @var array<string, mixed> $nesne */
                $ilgili = \in_array($event->type, ['checkout.session.completed', 'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true);
                if ('checkout.session.expired' === $event->type) {
                    // Ç59: ödenmeden kapanan oturumda completed hiç gelmez; eşleşme
                    // verisi (IP/UA) burada silinir — uygulamadan bağımsız.
                    $db->executeStatement(
                        'UPDATE odeme_oturumlari SET ip_adresi = NULL, user_agent = NULL WHERE stripe_checkout_session_id = :oturum',
                        ['oturum' => $nesne['id'] ?? null],
                    );

                    return;
                }
                if (!$ilgili) {
                    return;
                }
                $metadata = $nesne['metadata'] ?? null;
                $app = \is_array($metadata) ? ($metadata['app'] ?? null) : null;
                if (!\is_string($app) || '' === $app) {
                    $this->logger->warning('Stripe olayında metadata.app yok; uygulanmadı', ['event' => $event->id, 'type' => $event->type]);

                    return;
                }

                match ($event->type) {
                    'checkout.session.completed' => $this->linkEmail($db, $nesne, $app, $event->created),
                    default => $this->applyStatus($db, $nesne, $event->created, $app),
                };
            });
        } catch (UniqueConstraintViolationException) {
            // Bu olay daha önce işlendi; Stripe yeniden göndermesin diye 200.
        }
    }

    /** @param array<string, mixed> $session */
    private function linkEmail(Connection $db, array $session, string $app, int $eventTime): void
    {
        $sub = $session['subscription'] ?? null;
        $cus = $session['customer'] ?? null;
        if (($session['mode'] ?? null) !== 'subscription' || !\is_string($sub) || !\is_string($cus)) {
            return;
        }
        $detay = $session['customer_details'] ?? null;
        $email = \is_array($detay) && \is_string($detay['email'] ?? null) ? $detay['email'] : null;

        // Ç46: ödeme oturumu + tamamlanma anı — giriş bağlantısının penceresi
        // ve e-postası buradan.
        $db->executeStatement(
            'INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, email, app, stripe_checkout_session_id, odeme_tamamlandi_at)
             VALUES (:sub, :cus, :email, :app, :oturum, now())
             ON CONFLICT (stripe_subscription_id) DO UPDATE SET email = EXCLUDED.email,
                 stripe_checkout_session_id = EXCLUDED.stripe_checkout_session_id,
                 odeme_tamamlandi_at = EXCLUDED.odeme_tamamlandi_at, updated_at = now()',
            ['sub' => $sub, 'cus' => $cus, 'email' => $email, 'app' => $app, 'oturum' => $session['id'] ?? null],
        );
        $this->revenuecat->aboneligiKuyrugaAl($db, $app, $sub);
        // Atıf (A6): bu oturum bir huni ziyaretinden açıldıysa ödendi say.
        $odemeTamamlandi = 'paid' === ($session['payment_status'] ?? null);
        if ($odemeTamamlandi) {
            $db->executeStatement(
                'UPDATE odeme_oturumlari SET stripe_subscription_id = :sub, tamamlandi_at = now()
                 WHERE stripe_checkout_session_id = :oturum',
                ['sub' => $sub, 'oturum' => $session['id'] ?? null],
            );
            $this->donusum->kuyrugaAl($db, $session, $app, $eventTime);
        } elseif (\is_string($session['id'] ?? null)) {
            $db->executeStatement(
                'UPDATE odeme_oturumlari SET ip_adresi = NULL, user_agent = NULL WHERE stripe_checkout_session_id = :oturum',
                ['oturum' => $session['id']],
            );
        }
    }

    /** @param array<mixed> $sub */
    private function applyStatus(Connection $db, array $sub, int $eventCreated, string $app): void
    {
        $id = $sub['id'] ?? null;
        $cus = $sub['customer'] ?? null;
        $status = $sub['status'] ?? null;
        if (!\is_string($id) || !\is_string($cus) || !\is_string($status)) {
            return;
        }

        $db->executeStatement(
            'INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, status_event_created, app)
             VALUES (:id, :cus, :status, :created, :app)
             ON CONFLICT (stripe_subscription_id) DO UPDATE SET
                 stripe_customer_id = EXCLUDED.stripe_customer_id,
                 status = EXCLUDED.status,
                 status_event_created = EXCLUDED.status_event_created,
                 updated_at = now()
             WHERE subscriptions.status_event_created IS NULL
                OR subscriptions.status_event_created <= EXCLUDED.status_event_created',
            ['id' => $id, 'cus' => $cus, 'status' => $status, 'created' => $eventCreated, 'app' => $app],
        );
        $this->revenuecat->aboneligiKuyrugaAl($db, $app, $id);
    }
}
