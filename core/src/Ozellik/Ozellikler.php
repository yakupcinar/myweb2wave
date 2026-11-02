<?php

declare(strict_types=1);

namespace App\Ozellik;

use Doctrine\DBAL\Connection;

/**
 * T2 özellik deposu: ziyaretin ve kişinin verisi tek ad uzayında
 * (`ziyaret_ozellikleri` görünümü, göç Version20261102130000). Koşul motoru
 * (T3), kişiselleştirme ve CRM bu adları okur — ad değişmez, yalnız eklenir.
 */
final class Ozellikler
{
    /**
     * Sözlük: ad (".*" = önek + kimlik) → [tür, açıklama]. Seçmeli cevap
     * her zaman liste, serbest yanıt metin.
     *
     * @var array<string, array{string, string}>
     */
    public const SOZLUK = [
        'huni' => ['metin', 'ziyaretin gördüğü huni'],
        'huni.surum' => ['sayi', 'hunin yayın sürümü'],
        'utm.source' => ['metin', 'reklam kaynağı'],
        'utm.medium' => ['metin', 'reklam ortamı'],
        'utm.campaign' => ['metin', 'kampanya'],
        'utm.content' => ['metin', 'reklam içeriği'],
        'utm.term' => ['metin', 'anahtar kelime'],
        'izin.reklam' => ['metin', 'reklam ölçümü izni: evet / hayir (seçmediyse yok)'],
        'cevap.*' => ['liste|metin', 'adım kimliğiyle cevap: seçmelide seçenek kimlikleri listesi, serbest yanıtta metin'],
        'adim.son' => ['metin', 'ulaşılan en ileri adımın kimliği'],
        'odeme.durum' => ['metin', 'baslatildi / tamamlandi'],
        'abonelik.durum' => ['metin', 'Stripe abonelik durumu (active, past_due, canceled…)'],
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Adın sözlük tanımı; yoksa null (önekli adda kimlik boş olamaz).
     *
     * @return array{string, string}|null
     */
    public static function tanimi(string $ad): ?array
    {
        if (isset(self::SOZLUK[$ad])) {
            return self::SOZLUK[$ad];
        }
        $nokta = strpos($ad, '.');
        if (false === $nokta || $nokta === \strlen($ad) - 1) {
            return null;
        }

        return self::SOZLUK[substr($ad, 0, $nokta).'.*'] ?? null;
    }

    /**
     * Veritabanından canlı okur — iki çağrı farklı sonuç verebilir.
     *
     * @phpstan-impure
     *
     * @return array<string, mixed> ad → değer, ada göre sıralı
     */
    public function ziyaretin(string $ziyaret): array
    {
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $ziyaret)) {
            return [];
        }

        return self::harita($this->db->fetchAllAssociative(
            'SELECT ad, deger FROM ziyaret_ozellikleri WHERE ziyaret_id = :z ORDER BY ad',
            ['z' => $ziyaret],
        ));
    }

    /**
     * Kişinin ödemeyle bağlanan ziyaretleri birleşir; her adda en son değer.
     *
     * @phpstan-impure
     *
     * @return array<string, mixed>
     */
    public function kisinin(string $kisi): array
    {
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $kisi)) {
            return [];
        }

        return self::harita($this->db->fetchAllAssociative(
            'SELECT DISTINCT ON (o.ad) o.ad, o.deger
             FROM kisiler k
             JOIN subscriptions s ON s.email = k.email
             JOIN odeme_oturumlari oo ON oo.stripe_subscription_id = s.stripe_subscription_id
             JOIN ziyaret_ozellikleri o ON o.ziyaret_id = oo.ziyaret_id
             WHERE k.id = :k
             ORDER BY o.ad, o.zaman DESC',
            ['k' => $kisi],
        ));
    }

    /**
     * @param list<array<string, mixed>> $satirlar
     *
     * @return array<string, mixed>
     */
    private static function harita(array $satirlar): array
    {
        $h = [];
        foreach ($satirlar as $s) {
            // Görünümün sütunları: ad text, deger jsonb (sürücü metin verir).
            \assert(\is_string($s['ad']) && \is_string($s['deger']));
            $h[$s['ad']] = json_decode($s['deger'], true, 16, \JSON_THROW_ON_ERROR);
        }

        return $h;
    }
}
