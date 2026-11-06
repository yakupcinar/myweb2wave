<?php

declare(strict_types=1);

namespace App\Huni\Arac;

/**
 * AI araçlarının huni tanımı şeması (A0): sözleşmedeki HuniTanimi ve
 * bağlı olduğu bileşenler, kendi kendine yeten tek JSON Şeması olarak
 * (`$defs`). Kaynak contract/openapi.yaml — elle kopya yok; üretilen dosya
 * config/huni/tanim-semasi.json depoda durur (üretim imajında sözleşme
 * yok), `app:huni-semasi --kontrol` güncelliğini ölçer.
 */
final class HuniSemasi
{
    public const KOK = 'HuniTanimi';
    private const SOZLESME_REF = '#/components/schemas/';

    /**
     * @param array<string, mixed> $openapi ayrıştırılmış sözleşme
     *
     * @return array<string, mixed>
     */
    public static function uret(array $openapi): array
    {
        $bilesenler = \is_array($openapi['components'] ?? null) ? ($openapi['components']['schemas'] ?? null) : null;
        if (!\is_array($bilesenler) || !\is_array($bilesenler[self::KOK] ?? null)) {
            throw new \InvalidArgumentException('sözleşmede '.self::KOK.' yok');
        }
        // Kökten erişilebilen bileşenler (sıra: ad sırası — çıktı kararlı).
        $tanimlar = [];
        $bekleyen = [self::KOK];
        while ([] !== $bekleyen) {
            $ad = array_pop($bekleyen);
            if (isset($tanimlar[$ad])) {
                continue;
            }
            if (!\is_array($bilesenler[$ad] ?? null)) {
                throw new \InvalidArgumentException("sözleşmede {$ad} yok");
            }
            $tanimlar[$ad] = self::refleriCevir($bilesenler[$ad], $bekleyen);
        }
        ksort($tanimlar);

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$comment' => 'ÜRETİLDİ: contract/openapi.yaml → bin/console app:huni-semasi. Elle düzenleme.',
            '$defs' => $tanimlar,
        ];
    }

    /**
     * @param list<string> $bekleyen
     */
    private static function refleriCevir(mixed $dugum, array &$bekleyen): mixed
    {
        if (!\is_array($dugum)) {
            return $dugum;
        }
        $sonuc = [];
        foreach ($dugum as $anahtar => $deger) {
            if ('$ref' === $anahtar && \is_string($deger) && str_starts_with($deger, self::SOZLESME_REF)) {
                $ad = substr($deger, \strlen(self::SOZLESME_REF));
                $bekleyen[] = $ad;
                $sonuc[$anahtar] = '#/$defs/'.$ad;
                continue;
            }
            $sonuc[$anahtar] = self::refleriCevir($deger, $bekleyen);
        }

        return $sonuc;
    }
}
