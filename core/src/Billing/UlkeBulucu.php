<?php

declare(strict_types=1);

namespace App\Billing;

use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * M1c: ziyaretçinin ülkesi — para birimini seçmek için (kullanıcı kararı
 * 2026-11-04; rakiplerin üçü de IP'den, web2wave modeli). Önce test
 * parametresi (`ulke=DE`, web2wave'in `w2w_country_code`'u gibi), yoksa IP'den
 * DB-IP IP-to-Country Lite (CC BY 4.0 — huni sayfasında atıf bağlantısı
 * şart; okuyucu Apache-2.0). Dosya depoda değil: `app:ulke-verisi` indirir.
 * Dosya yoksa ya da IP bulunamazsa null → planın ana para birimi.
 */
final class UlkeBulucu
{
    private const KOD = '/^[A-Z]{2}$/';

    private ?Reader $okuyucu = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/ulke/dbip-country-lite.mmdb')]
        private readonly string $dosya,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ulke(?string $ip, mixed $test): ?string
    {
        if (\is_string($test) && 1 === preg_match(self::KOD, $test)) {
            return $test;
        }
        if (null === $ip || false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return null;
        }
        try {
            $kayit = $this->okuyucu()?->get($ip);
        } catch (\Throwable $e) {
            $this->logger->warning('Ülke verisi okunamadı', ['exception' => $e]);

            return null;
        }
        $kod = \is_array($kayit) && \is_array($kayit['country'] ?? null) ? ($kayit['country']['iso_code'] ?? null) : null;

        return \is_string($kod) && 1 === preg_match(self::KOD, $kod) ? $kod : null;
    }

    private function okuyucu(): ?Reader
    {
        if (null === $this->okuyucu) {
            if (!is_file($this->dosya)) {
                $this->logger->warning('Ülke verisi yok — para birimi planın ana birimi (bin/console app:ulke-verisi)', ['dosya' => $this->dosya]);

                return null;
            }
            $this->okuyucu = new Reader($this->dosya);
        }

        return $this->okuyucu;
    }
}
