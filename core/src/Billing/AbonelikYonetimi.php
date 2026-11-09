<?php

declare(strict_types=1);

namespace App\Billing;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * M6a (A19): panelden abonelik yönetimi. İptal dönem sonunda (geri
 * alınabilir); iade son tahsil edilen faturanın kalanının tamamı. Stripe'ın
 * döndürdüğü durum hemen yazılır — webhook gecikse de panel doğru göstersin;
 * webhook aynı değeri yazar.
 */
final readonly class AbonelikYonetimi
{
    public function __construct(
        private Connection $db,
        private StripeClient $stripe,
        private SubscriptionSync $sync,
        private LoggerInterface $logger,
    ) {
    }

    /** @return string|null hata metni ya da null (tamam) */
    public function donemSonundaIptal(string $abonelik, bool $iptal): ?string
    {
        if (false === $this->db->fetchOne('SELECT 1 FROM subscriptions WHERE stripe_subscription_id = :s', ['s' => $abonelik])) {
            return 'bilinmeyen abonelik';
        }
        try {
            $sub = $this->stripe->subscriptions->update($abonelik, ['cancel_at_period_end' => $iptal])->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe aboneliği güncellenemedi', ['abonelik' => $abonelik, 'exception' => $e]);

            return 'Stripe isteği reddetti: '.self::kisa($e);
        }
        $this->db->executeStatement(
            'UPDATE subscriptions SET iptal_donem_sonu = :iptal, updated_at = now() WHERE stripe_subscription_id = :s',
            ['iptal' => true === ($sub['cancel_at_period_end'] ?? null), 's' => $abonelik],
            ['iptal' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return null;
    }

    /** @return string|null hata metni ya da null (tamam) */
    public function sonFaturayiIadeEt(string $abonelik): ?string
    {
        /** @var array{stripe_invoice_id: string, tutar_kucuk: int, iade_kucuk: int}|false $f */
        $f = $this->db->fetchAssociative(
            'SELECT stripe_invoice_id, tutar_kucuk, iade_kucuk FROM odeme_faturalari WHERE stripe_subscription_id = :s ORDER BY odendi_at DESC, stripe_invoice_id DESC LIMIT 1',
            ['s' => $abonelik],
        );
        if (false === $f) {
            return 'iade edilecek tahsil edilmiş fatura yok';
        }
        if ($f['iade_kucuk'] >= $f['tutar_kucuk']) {
            return 'son fatura zaten iade edildi';
        }
        try {
            // Fatura ödeme kimliği taşımaz (API 2026-09-30) — fatura ödemesinden.
            $pi = null;
            foreach ($this->stripe->invoicePayments->all(['invoice' => $f['stripe_invoice_id'], 'limit' => 10])->data as $o) {
                $odeme = $o->toArray();
                $p = $odeme['payment'] ?? null;
                if ('paid' === ($odeme['status'] ?? null) && \is_array($p) && \is_string($p['payment_intent'] ?? null)) {
                    $pi = $p['payment_intent'];
                    break;
                }
            }
            if (null === $pi) {
                return 'faturanın ödemesi Stripe\'ta bulunamadı';
            }
            $iade = $this->stripe->refunds->create(['payment_intent' => $pi, 'amount' => $f['tutar_kucuk'] - $f['iade_kucuk'], 'metadata' => ['fatura' => $f['stripe_invoice_id']]]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe iadesi yapılamadı', ['abonelik' => $abonelik, 'exception' => $e]);

            return 'Stripe isteği reddetti: '.self::kisa($e);
        }
        $this->db->transactional(function (Connection $db) use ($pi, $f, $iade): void {
            $db->executeStatement('INSERT INTO fatura_odemeleri (stripe_payment_intent_id, stripe_invoice_id) VALUES (:pi, :f) ON CONFLICT DO NOTHING', ['pi' => $pi, 'f' => $f['stripe_invoice_id']]);
            $this->sync->iadeYaz($db, ['payment_intent' => $pi, 'amount_refunded' => $f['iade_kucuk'] + $iade->amount]);
        });

        return null;
    }

    private static function kisa(ApiErrorException $e): string
    {
        return mb_substr($e->getError()->message ?? $e->getMessage(), 0, 200);
    }
}
