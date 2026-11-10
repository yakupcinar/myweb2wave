<?php

declare(strict_types=1);

namespace App\Huni;

use Doctrine\DBAL\Connection;

/**
 * M9c (A23): yeni hunilere kopyalanan görünüm. Yönetici bir huniyi "örnek"
 * seçer; temasının, logosunun ve SON paywall adımının (son adım paywall'dır,
 * M2c) o anki kopyası saklanır.
 *
 * - Kopya modeli (kullanıcı kararı): varsayılan değişince var olan huniler ve
 *   yayınlanmış sürümler değişmez — ziyaretçinin gördüğü dondurulmuş kalır.
 * - Boş huni temayı, logoyu ve paywall adımının tamamını alır.
 * - Şablondan açılan huni temayı, logoyu ve paywall'ın UYGULAMAYA ÖZGÜ
 *   alanlarını alır (planlar, güvenceler, şart onayı, yasal adresler,
 *   downsell); metinler şablonun (niş metni).
 * - "Varsayılan huni" (kural tutmayınca açılan) ile karışmasın: bu yalnız
 *   yeni huninin başlangıç görünümü.
 */
final readonly class Varsayilanlar
{
    /** Paywall'ın uygulamaya özgü alanları — şablondan açılan huniye de gelir. */
    public const UYGULAMA_ALANLARI = ['plan', 'planlar', 'onerilen', 'guvenceler', 'sartOnayi', 'sartlar', 'gizlilik', 'iptal', 'downsell'];

    public function __construct(private Connection $db)
    {
    }

    /**
     * Huninin taslağından kopya alır; ayar satırı yoksa false.
     *
     * @param array<mixed> $taslak
     */
    public function al(string $app, string $huni, array $taslak): bool
    {
        $paywall = null;
        foreach (\is_array($taslak['adimlar'] ?? null) ? $taslak['adimlar'] : [] as $adim) {
            if (\is_array($adim) && 'paywall' === ($adim['tur'] ?? null)) {
                $paywall = $adim;
            }
        }
        unset($paywall['id'], $paywall['tur']);
        $kopya = ['kaynak' => $huni, 'alindi' => (new \DateTimeImmutable())->format(\DATE_ATOM)]
            + (isset($taslak['tema']) ? ['tema' => $taslak['tema']] : [])
            + (isset($taslak['logo']) ? ['logo' => $taslak['logo']] : [])
            + (\is_array($paywall) && [] !== $paywall ? ['paywall' => $paywall] : []);

        return 1 === $this->db->executeStatement(
            'UPDATE uygulama_ayarlari SET varsayilan = CAST(? AS jsonb), updated_at = now() WHERE app = ?',
            [json_encode($kopya, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), $app],
        );
    }

    /** @return array<string, mixed>|null */
    public function oku(string $app): ?array
    {
        $v = $this->db->fetchOne('SELECT varsayilan FROM uygulama_ayarlari WHERE app = ?', [$app]);
        $kopya = \is_string($v) ? json_decode($v, true) : null;

        return \is_array($kopya) ? array_filter($kopya, \is_string(...), \ARRAY_FILTER_USE_KEY) : null;
    }

    public function kaldir(string $app): void
    {
        $this->db->executeStatement('UPDATE uygulama_ayarlari SET varsayilan = NULL, updated_at = now() WHERE app = ?', [$app]);
    }

    /**
     * Yeni huninin taslağına uygular; varsayılan yoksa taslak aynen döner.
     *
     * @param array<mixed> $taslak
     *
     * @return array<mixed>
     */
    public function uygula(string $app, array $taslak, bool $sablondan): array
    {
        $v = $this->oku($app);
        if (null === $v) {
            return $taslak;
        }
        foreach (['tema', 'logo'] as $alan) {
            if (isset($v[$alan])) {
                $taslak[$alan] = $v[$alan];
            }
        }
        $pw = \is_array($v['paywall'] ?? null) ? $v['paywall'] : null;
        if (null === $pw || !\is_array($taslak['adimlar'] ?? null)) {
            return $taslak;
        }
        foreach ($taslak['adimlar'] as $i => $adim) {
            if (!\is_array($adim) || 'paywall' !== ($adim['tur'] ?? null)) {
                continue;
            }
            if (!$sablondan) {
                $taslak['adimlar'][$i] = ['id' => $adim['id'] ?? 'paywall', 'tur' => 'paywall'] + $pw;
                continue;
            }
            foreach (self::UYGULAMA_ALANLARI as $alan) {
                if (\array_key_exists($alan, $pw)) {
                    $adim[$alan] = $pw[$alan];
                } else {
                    unset($adim[$alan]);
                }
            }
            $taslak['adimlar'][$i] = $adim;
        }

        return $taslak;
    }
}
