<?php

declare(strict_types=1);

namespace App\Billing;

use App\Kimlik\DogrulamaKodu;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * M6a (A19): panelden abonelik yönetimi. İptal dönem sonunda (geri
 * alınabilir); iade son tahsil edilen faturanın kalanının tamamı. Stripe'ın
 * döndürdüğü durum hemen yazılır — webhook gecikse de panel doğru göstersin;
 * webhook aynı değeri yazar. M6c (A28): duraklat / sürdür. Ç150: ödeme
 * formunda yanlış yazılmış e-postayı düzelt.
 */
final readonly class AbonelikYonetimi
{
    public const DURAKLATMA_AYLARI = [1, 2, 3];

    public function __construct(
        private Connection $db,
        private StripeClient $stripe,
        private SubscriptionSync $sync,
        private LoggerInterface $logger,
        private RateLimiterFactory $epostaDuzeltmeLimiter,
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

    /**
     * M6c (A28): $ay (1–3) ay duraklat — tahsilat durur (void), Stripe o
     * tarihte kendisi sürdürür; null hemen sürdürür (stripe-php boş alan
     * gönderir). Stripe'ın yanıtı yazılır.
     *
     * @return string|null hata metni ya da null (tamam)
     */
    public function duraklat(string $abonelik, ?int $ay): ?string
    {
        if (false === $this->db->fetchOne('SELECT 1 FROM subscriptions WHERE stripe_subscription_id = :s', ['s' => $abonelik])) {
            return 'bilinmeyen abonelik';
        }
        if (null !== $ay && !\in_array($ay, self::DURAKLATMA_AYLARI, true)) {
            return 'süre 1, 2 ya da 3 ay olmalı';
        }
        try {
            $sub = $this->stripe->subscriptions->update($abonelik, ['pause_collection' => null === $ay ? null : [
                'behavior' => 'void',
                'resumes_at' => (new \DateTimeImmutable("+{$ay} months"))->getTimestamp(),
            ]])->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe aboneliği duraklatılamadı', ['abonelik' => $abonelik, 'exception' => $e]);

            return 'Stripe isteği reddetti: '.self::kisa($e);
        }
        $durak = \is_array($sub['pause_collection'] ?? null) ? $sub['pause_collection'] : null;
        $this->db->executeStatement(
            'UPDATE subscriptions SET duraklatildi = :durak, duraklatma_bitis = to_timestamp(CAST(:bitis AS bigint)), updated_at = now() WHERE stripe_subscription_id = :s',
            ['durak' => null !== $durak, 'bitis' => null !== $durak && \is_int($durak['resumes_at'] ?? null) ? $durak['resumes_at'] : null, 's' => $abonelik],
            ['durak' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return null;
    }

    /**
     * Ç150: ödeme formunda yanlış yazılmış e-postayı düzelt. Talebi yapanın
     * ödeyen olduğunu kanıtı: müşterinin söylediği kartın son 4 hanesi,
     * Stripe'taki ödeme yöntemiyle eşleşmeli (panel son 4'ü göstermez).
     * Yanlış deneme abonelik başına saatte 5 — destek tahminle kandırılamaz.
     * Eski e-posta uygulamada doğrulanmışsa (bağlı) düzeltilmez: yanlış
     * değildir, düzeltme aboneliği başkasına taşımak olur. Yeni adres hak
     * vermez; sahibi yine kod ya da giriş bağlantısıyla kanıtlar (Hak).
     * Günlüğe abonelik ve sonuç yazılır, e-posta değil.
     *
     * @return string|null hata metni ya da null (tamam)
     */
    public function epostaDuzelt(string $abonelik, string $yeni, string $son4): ?string
    {
        /** @var array{email: ?string, app: string, stripe_customer_id: string}|false $s */
        $s = $this->db->fetchAssociative('SELECT email, app, stripe_customer_id FROM subscriptions WHERE stripe_subscription_id = :s', ['s' => $abonelik]);
        if (false === $s) {
            return 'bilinmeyen abonelik';
        }
        $yeni = DogrulamaKodu::normal($yeni);
        if (false === filter_var($yeni, \FILTER_VALIDATE_EMAIL)) {
            return 'yeni e-posta geçersiz';
        }
        if ($yeni === DogrulamaKodu::normal((string) $s['email'])) {
            return 'yeni e-posta eskisiyle aynı';
        }
        if (1 !== preg_match('/^\d{4}$/', $son4)) {
            return 'kartın son 4 hanesi 4 rakam olmalı';
        }
        if (false !== $this->db->fetchOne(
            'SELECT 1 FROM baglantilar b JOIN kisiler k ON k.id = b.kisi_id WHERE b.app = :app AND lower(k.email) = :eski',
            ['app' => $s['app'], 'eski' => DogrulamaKodu::normal((string) $s['email'])],
        )) {
            return 'eski e-posta uygulamada doğrulanmış; yanlış yazılmamış, düzeltilemez';
        }
        $sinir = $this->epostaDuzeltmeLimiter->create($abonelik);
        if (0 === $sinir->consume(0)->getRemainingTokens()) {
            return 'bu abonelik için çok fazla yanlış deneme; bir saat sonra yeniden deneyin';
        }
        try {
            $sub = $this->stripe->subscriptions->retrieve($abonelik, ['expand' => ['default_payment_method', 'customer.invoice_settings.default_payment_method']])->toArray();
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe aboneliği okunamadı', ['abonelik' => $abonelik, 'exception' => $e]);

            return 'Stripe isteği reddetti: '.self::kisa($e);
        }
        $pm = $sub['default_payment_method'] ?? null;
        $musteri = $sub['customer'] ?? null;
        if (!\is_array($pm) && \is_array($musteri) && \is_array($musteri['invoice_settings'] ?? null)) {
            $pm = $musteri['invoice_settings']['default_payment_method'] ?? null;
        }
        $kart = \is_array($pm) && \is_array($pm['card'] ?? null) && \is_string($pm['card']['last4'] ?? null) ? $pm['card']['last4'] : null;
        if (null === $kart) {
            return 'aboneliğin kart bilgisi Stripe\'ta yok';
        }
        if (!hash_equals($kart, $son4)) {
            $sinir->consume();
            $this->logger->warning('abonelik e-postası düzeltilemedi: kart son 4 eşleşmedi', ['abonelik' => $abonelik]);

            return 'kartın son 4 hanesi eşleşmiyor';
        }
        try {
            // Makbuz ve portal doğru adrese gitsin.
            $this->stripe->customers->update($s['stripe_customer_id'], ['email' => $yeni]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe müşteri e-postası güncellenemedi', ['abonelik' => $abonelik, 'exception' => $e]);

            return 'Stripe isteği reddetti: '.self::kisa($e);
        }
        $this->db->executeStatement('UPDATE subscriptions SET email = :e, updated_at = now() WHERE stripe_subscription_id = :s', ['e' => $yeni, 's' => $abonelik]);
        $this->logger->info('abonelik e-postası düzeltildi', ['abonelik' => $abonelik]);

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
