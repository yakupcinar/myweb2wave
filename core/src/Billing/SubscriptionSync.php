<?php

declare(strict_types=1);

namespace App\Billing;

use App\Atif\DonusumAktarimi;
use App\Olay\Olaylar;
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
                $ilgili = \in_array($event->type, ['checkout.session.completed', 'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted', 'invoice.paid'], true);
                if ('checkout.session.expired' === $event->type) {
                    // Ç59: ödenmeden kapanan oturumda completed hiç gelmez; eşleşme
                    // verisi (IP/UA) burada silinir — uygulamadan bağımsız.
                    $db->executeStatement(
                        'UPDATE odeme_oturumlari SET ip_adresi = NULL, user_agent = NULL WHERE stripe_checkout_session_id = :oturum',
                        ['oturum' => $nesne['id'] ?? null],
                    );

                    return;
                }
                // M6a: fatura ↔ ödeme bağı ve iade — yalnız kimlikler, uygulamadan bağımsız
                // (bu nesnelerde metadata.app yok; fatura zaten uygulamasıyla yazılı).
                if ('invoice_payment.paid' === $event->type) {
                    $this->odemeBagla($db, $nesne);

                    return;
                }
                if ('charge.refunded' === $event->type) {
                    $this->iadeYaz($db, $nesne);

                    return;
                }
                if (!$ilgili) {
                    return;
                }
                // M5b: faturanın kendi metadata'sı boş; uygulama aboneliğin metadata'sında
                // (parent.subscription_details — API 2026-09-30, ölçüldü).
                $metadata = 'invoice.paid' === $event->type ? self::faturaAboneligi($nesne)['metadata'] : ($nesne['metadata'] ?? null);
                $app = \is_array($metadata) ? ($metadata['app'] ?? null) : null;
                if (!\is_string($app) || '' === $app) {
                    $this->logger->warning('Stripe olayında metadata.app yok; uygulanmadı', ['event' => $event->id, 'type' => $event->type]);

                    return;
                }

                match ($event->type) {
                    'checkout.session.completed' => $this->linkEmail($db, $nesne, $app, $event->created),
                    'invoice.paid' => $this->faturaYaz($db, $nesne, $app, $event->created),
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
        // M4b (A17): deney dönüşümü abonelik başlamasıdır (deneme dahil).
        // Kartlı denemede Stripe yine "paid" der, tutar 0 (ölçüldü) — "ödedi"
        // tutardan okunur. Tutar gelmezse abonelik yine yazılır.
        $bag = false;
        if ($odemeTamamlandi || 'no_payment_required' === ($session['payment_status'] ?? null)) {
            $tutar = $session['amount_total'] ?? null;
            $para = $session['currency'] ?? null;
            $tutarli = \is_int($tutar) && $tutar >= 0 && \is_string($para) && 1 === preg_match('/^[a-z]{3}$/', $para);
            $bag = $db->fetchAssociative(
                'UPDATE odeme_oturumlari SET stripe_subscription_id = :sub, abonelik_at = coalesce(abonelik_at, now()),
                        tutar_kucuk = coalesce(tutar_kucuk, :tutar), para_birimi = coalesce(para_birimi, :para)
                 WHERE stripe_checkout_session_id = :oturum
                 RETURNING ziyaret_id::text AS ziyaret, old.stripe_subscription_id IS DISTINCT FROM new.stripe_subscription_id AS yeni',
                ['sub' => $sub, 'oturum' => $session['id'] ?? null, 'tutar' => $tutarli ? $tutar : null, 'para' => $tutarli ? $para : null],
            );
        }
        if ($odemeTamamlandi) {
            $odeme = $db->fetchAssociative(
                'UPDATE odeme_oturumlari SET stripe_subscription_id = :sub, tamamlandi_at = now()
                 WHERE stripe_checkout_session_id = :oturum RETURNING ziyaret_id::text AS ziyaret, old.tamamlandi_at IS NULL AS ilk',
                ['sub' => $sub, 'oturum' => $session['id'] ?? null],
            );
            if (\is_array($odeme) && \is_string($odeme['ziyaret']) && true === $odeme['ilk']) {
                Olaylar::ekle($db, $odeme['ziyaret'], 'odeme.tamamlandi');
            }
            $this->donusum->kuyrugaAl($db, $session, $app, $eventTime);
        } elseif (\is_string($session['id'] ?? null)) {
            $db->executeStatement(
                'UPDATE odeme_oturumlari SET ip_adresi = NULL, user_agent = NULL WHERE stripe_checkout_session_id = :oturum',
                ['oturum' => $session['id']],
            );
        }
        // M5a: abonelik olayı oturumdan önce geldiyse durum zaten yazılı; ziyaret
        // aboneliğe şimdi bağlandı — durumu o ziyaretin çizelgesine de ekle.
        if (\is_array($bag) && \is_string($bag['ziyaret']) && true === $bag['yeni']) {
            $durum = $db->fetchOne('SELECT status FROM subscriptions WHERE stripe_subscription_id = :sub', ['sub' => $sub]);
            if (\is_string($durum)) {
                Olaylar::ekle($db, $bag['ziyaret'], 'abonelik.durum', ['durum' => $durum]);
            }
            // M5b: ilk faturanın invoice.paid'i çoğu zaman oturumdan önce gelir.
            foreach ($db->fetchAllAssociative('SELECT tutar_kucuk, para_birimi, neden FROM odeme_faturalari WHERE stripe_subscription_id = :sub ORDER BY odendi_at, stripe_invoice_id', ['sub' => $sub]) as $f) {
                Olaylar::ekle($db, $bag['ziyaret'], 'odeme.fatura', self::faturaVerisi($f));
            }
        }
    }

    /**
     * M5b (A18): tahsil edilen fatura (tutar > 0) — kohort geliri. Tekrar gelen
     * fatura bir kez; aboneliğe bağlı her ziyarete olay. Tutar 0 (deneme
     * başlangıcı) gelir değil, yazılmaz.
     *
     * @param array<string, mixed> $fatura
     */
    private function faturaYaz(Connection $db, array $fatura, string $app, int $eventTime): void
    {
        $sub = self::faturaAboneligi($fatura)['abonelik'];
        $id = $fatura['id'] ?? null;
        $tutar = $fatura['amount_paid'] ?? null;
        $para = $fatura['currency'] ?? null;
        if (!\is_string($id) || !\is_int($tutar) || $tutar <= 0 || !\is_string($para) || 1 !== preg_match('/^[a-z]{3}$/', $para)) {
            return;
        }
        $gecis = $fatura['status_transitions'] ?? null;
        $odendi = \is_array($gecis) ? ($gecis['paid_at'] ?? null) : null;
        $neden = $fatura['billing_reason'] ?? null;
        $yeni = $db->fetchAssociative(
            'INSERT INTO odeme_faturalari (stripe_invoice_id, stripe_subscription_id, app, tutar_kucuk, para_birimi, neden, odendi_at)
             VALUES (:id, :sub, :app, :tutar, :para, :neden, to_timestamp(:odendi))
             ON CONFLICT (stripe_invoice_id) DO NOTHING RETURNING tutar_kucuk, para_birimi, neden',
            ['id' => $id, 'sub' => $sub, 'app' => $app, 'tutar' => $tutar, 'para' => $para, 'neden' => \is_string($neden) ? $neden : null,
                'odendi' => \is_int($odendi) ? $odendi : $eventTime],
        );
        if (false === $yeni) {
            return;
        }
        foreach ($db->fetchFirstColumn('SELECT DISTINCT ziyaret_id::text FROM odeme_oturumlari WHERE stripe_subscription_id = :sub', ['sub' => $sub]) as $ziyaret) {
            \assert(\is_string($ziyaret));
            Olaylar::ekle($db, $ziyaret, 'odeme.fatura', self::faturaVerisi($yeni));
        }
    }

    /**
     * M6a: invoice_payment.paid — iade ödemeye (payment_intent) gelir, fatura
     * ödeme kimliği taşımaz; bağ burada tutulur. Sıra fark etmez (fatura sonra gelebilir).
     *
     * @param array<string, mixed> $odeme
     */
    private function odemeBagla(Connection $db, array $odeme): void
    {
        $fatura = $odeme['invoice'] ?? null;
        $p = $odeme['payment'] ?? null;
        $pi = \is_array($p) ? ($p['payment_intent'] ?? null) : null;
        if (\is_string($fatura) && \is_string($pi)) {
            $db->executeStatement(
                'INSERT INTO fatura_odemeleri (stripe_payment_intent_id, stripe_invoice_id) VALUES (:pi, :fatura) ON CONFLICT DO NOTHING',
                ['pi' => $pi, 'fatura' => $fatura],
            );
        }
    }

    /**
     * M6a (A19): charge.refunded — faturanın iadesi Stripe'ın birikimli
     * amount_refunded'ı (tutarla sınırlı); azalmaz, tekrar olay iki kez düşmez.
     * Artış aboneliğe bağlı her ziyarete odeme.iade olayı.
     *
     * @param array<string, mixed> $odeme
     */
    public function iadeYaz(Connection $db, array $odeme): void
    {
        $pi = $odeme['payment_intent'] ?? null;
        $iade = $odeme['amount_refunded'] ?? null;
        if (!\is_string($pi) || !\is_int($iade) || $iade <= 0) {
            return;
        }
        $f = $db->fetchAssociative(
            'UPDATE odeme_faturalari f SET iade_kucuk = least(f.tutar_kucuk, :iade)
             FROM fatura_odemeleri m WHERE m.stripe_payment_intent_id = :pi AND f.stripe_invoice_id = m.stripe_invoice_id AND f.iade_kucuk < least(f.tutar_kucuk, :iade)
             RETURNING f.stripe_subscription_id, f.para_birimi, f.iade_kucuk - old.iade_kucuk AS artis',
            ['pi' => $pi, 'iade' => $iade],
        );
        if (false === $f) {
            return;
        }
        foreach ($db->fetchFirstColumn('SELECT DISTINCT ziyaret_id::text FROM odeme_oturumlari WHERE stripe_subscription_id = :sub', ['sub' => $f['stripe_subscription_id']]) as $ziyaret) {
            \assert(\is_string($ziyaret));
            Olaylar::ekle($db, $ziyaret, 'odeme.iade', ['tutar' => is_numeric($f['artis']) ? (int) $f['artis'] : 0, 'paraBirimi' => \is_string($f['para_birimi']) ? $f['para_birimi'] : '']);
        }
    }

    /**
     * @param array<string, mixed> $fatura
     *
     * @return array{abonelik: ?string, metadata: mixed}
     */
    private static function faturaAboneligi(array $fatura): array
    {
        $parent = $fatura['parent'] ?? null;
        $detay = \is_array($parent) ? ($parent['subscription_details'] ?? null) : null;
        $sub = \is_array($detay) ? ($detay['subscription'] ?? null) : null;

        return ['abonelik' => \is_string($sub) ? $sub : null, 'metadata' => \is_array($detay) ? ($detay['metadata'] ?? null) : null];
    }

    /**
     * @param array<string, mixed> $f
     *
     * @return array{tutar: int, paraBirimi: string, neden: ?string}
     */
    private static function faturaVerisi(array $f): array
    {
        return ['tutar' => is_numeric($f['tutar_kucuk']) ? (int) $f['tutar_kucuk'] : 0, 'paraBirimi' => \is_string($f['para_birimi']) ? $f['para_birimi'] : '',
            'neden' => \is_string($f['neden']) ? $f['neden'] : null];
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

        // M6a: dönem sonu aboneliğin kaleminde (API 2026-09-30, ölçüldü); tek kalemli abonelik.
        $kalemler = $sub['items'] ?? null;
        $kalem = \is_array($kalemler) && \is_array($kalemler['data'] ?? null) ? ($kalemler['data'][0] ?? null) : null;
        $donemSonu = \is_array($kalem) && \is_int($kalem['current_period_end'] ?? null) ? $kalem['current_period_end'] : null;
        // M5a: RETURNING yalnız yazılan satırda; eski olay reddedildiyse satır yok.
        $degisti = $db->fetchOne(
            'INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, status_event_created, app, iptal_donem_sonu, donem_sonu)
             VALUES (:id, :cus, :status, :created, :app, :iptal, to_timestamp(CAST(:donem AS bigint)))
             ON CONFLICT (stripe_subscription_id) DO UPDATE SET
                 stripe_customer_id = EXCLUDED.stripe_customer_id,
                 status = EXCLUDED.status,
                 status_event_created = EXCLUDED.status_event_created,
                 iptal_donem_sonu = EXCLUDED.iptal_donem_sonu,
                 donem_sonu = EXCLUDED.donem_sonu,
                 updated_at = now()
             WHERE subscriptions.status_event_created IS NULL
                OR subscriptions.status_event_created <= EXCLUDED.status_event_created
             RETURNING old.status IS DISTINCT FROM new.status',
            ['id' => $id, 'cus' => $cus, 'status' => $status, 'created' => $eventCreated, 'app' => $app,
                'iptal' => true === ($sub['cancel_at_period_end'] ?? null), 'donem' => $donemSonu],
            ['iptal' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        if (true === $degisti) {
            foreach ($db->fetchFirstColumn('SELECT DISTINCT ziyaret_id::text FROM odeme_oturumlari WHERE stripe_subscription_id = :id', ['id' => $id]) as $ziyaret) {
                \assert(\is_string($ziyaret));
                Olaylar::ekle($db, $ziyaret, 'abonelik.durum', ['durum' => $status]);
            }
        }
        $this->revenuecat->aboneligiKuyrugaAl($db, $app, $id);
    }
}
