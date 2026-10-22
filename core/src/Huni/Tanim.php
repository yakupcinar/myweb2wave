<?php

declare(strict_types=1);

namespace App\Huni;

/**
 * Huni tanımının biçimi (A7) — yayından ÖNCE burada doğrulanır; huni
 * sayfası yalnız buradan geçmiş tanımı görür. Sözleşme: contract
 * `HuniTanimi`. Serbest HTML yok: metinler düz metin olarak gösterilir.
 */
final class Tanim
{
    public const KIMLIK = '/^[a-z0-9_-]{1,64}$/';
    public const AZAMI_ADIM = 50;

    /** Alan → [en az, en çok] karakter. */
    private const UZUNLUK = [
        'sayfaBasligi' => [1, 120], 'baslik' => [1, 200], 'metin' => [0, 1000],
        'dugme' => [1, 60], 'soru' => [1, 200], 'secenek' => [1, 120],
    ];

    /** Tür → zorunlu metin alanları (başka alan kabul edilmez). */
    private const TURLER = [
        'tanitim' => ['baslik', 'metin', 'dugme'],
        'soru' => ['soru'],
        'paywall' => ['baslik', 'metin', 'dugme'],
    ];

    /**
     * @param list<string> $planlar bu uygulamanın planları (config uygulamalar)
     *
     * @return list<string> boşsa geçerli
     */
    public static function hatalar(mixed $tanim, array $planlar): array
    {
        // tema isteğe bağlı (E2.2): yoksa huni sayfası varsayılanı kullanır.
        if (!\is_array($tanim) || !self::anahtarlar($tanim, \array_key_exists('tema', $tanim) ? ['sayfaBasligi', 'adimlar', 'tema'] : ['sayfaBasligi', 'adimlar'])) {
            return ['tanım yalnız sayfaBasligi, adimlar ve (isteğe bağlı) tema içermeli'];
        }
        $h = \array_key_exists('tema', $tanim) ? Tema::hatalar($tanim['tema']) : [];
        self::metin($tanim['sayfaBasligi'], 'sayfaBasligi', 'sayfaBasligi', $h);
        $adimlar = $tanim['adimlar'];
        if (!\is_array($adimlar) || !array_is_list($adimlar) || \count($adimlar) < 2 || \count($adimlar) > self::AZAMI_ADIM) {
            return [...$h, 'adimlar 2–'.self::AZAMI_ADIM.' öğeli bir liste olmalı'];
        }

        // H5: hedef yalnız İLERİ — kimlik → sıra (döngü olamaz, doğrulama tek geçiş).
        $sira = [];
        foreach ($adimlar as $i => $adim) {
            if (\is_array($adim) && \is_string($adim['id'] ?? null) && !isset($sira[$adim['id']])) {
                $sira[$adim['id']] = $i;
            }
        }
        $gorulen = [];
        foreach ($adimlar as $i => $adim) {
            $yer = 'adım '.($i + 1);
            $tur = \is_array($adim) ? ($adim['tur'] ?? null) : null;
            if (!\is_array($adim) || !\is_string($tur) || !isset(self::TURLER[$tur])) {
                $h[] = "{$yer}: tür tanitim, soru ya da paywall olmalı";
                continue;
            }
            $id = $adim['id'] ?? null;
            if (!\is_string($id) || 1 !== preg_match(self::KIMLIK, $id)) {
                $h[] = "{$yer}: kimlik küçük harf, rakam, - ve _ (1–64)";
            } elseif (isset($gorulen[$id])) {
                $h[] = "{$yer}: kimlik '{$id}' tekrar ediyor";
            } else {
                $gorulen[$id] = true;
            }
            $beklenen = ['id', 'tur', ...self::TURLER[$tur], ...match ($tur) {
                'soru' => ['secenekler'], 'paywall' => ['plan'], default => [],
            }];
            if (\array_key_exists('ilerlemeGoster', $adim)) {
                $beklenen[] = 'ilerlemeGoster';
                if (!\is_bool($adim['ilerlemeGoster'])) {
                    $h[] = "{$yer}: ilerlemeGoster true ya da false olmalı";
                }
            }
            // E2.3: tanıtım adımında isteğe bağlı görsel.
            if ('tanitim' === $tur && \array_key_exists('gorsel', $adim)) {
                $beklenen[] = 'gorsel';
                self::gorsel($adim['gorsel'], $yer, $h);
            }
            // H5: tanıtım düğmesi de atlayabilir — dallar ortak adımda birleşsin.
            if ('tanitim' === $tur && \array_key_exists('hedef', $adim)) {
                $beklenen[] = 'hedef';
                self::hedef($adim['hedef'], $sira, $i, $yer, $h);
            }
            if (!self::anahtarlar($adim, $beklenen)) {
                $h[] = "{$yer}: alanlar tam olarak ".implode(', ', $beklenen).' olmalı';
                continue;
            }
            foreach (self::TURLER[$tur] as $alan) {
                self::metin($adim[$alan], $alan, "{$yer} {$alan}", $h);
            }
            if ('soru' === $tur) {
                self::secenekler($adim['secenekler'], $sira, $i, $yer, $h);
            }
            if ('paywall' === $tur && !\in_array($adim['plan'], $planlar, true)) {
                $h[] = "{$yer}: plan bu uygulamada yok";
            }
            if (('paywall' === $tur) !== ($i === \count($adimlar) - 1)) {
                $h[] = "{$yer}: paywall tam bir kez ve EN SONDA olmalı";
            }
        }

        return $h;
    }

    /**
     * Anahtar KÜMESİ eşit mi — sıra değil: Postgres JSONB nesne anahtarlarını
     * yeniden sıralar, veritabanından dönen tanım sıra yüzünden reddedilmesin.
     *
     * @param array<mixed> $dizi
     * @param list<string> $beklenen
     */
    private static function anahtarlar(array $dizi, array $beklenen): bool
    {
        $var = array_map(strval(...), array_keys($dizi));
        sort($var);
        sort($beklenen);

        return $var === $beklenen;
    }

    /**
     * Tanımdaki bütün görsel kimlikleri (Huniler bunların bu uygulamaya
     * yüklenmiş olduğunu ayrıca denetler).
     *
     * @return list<string>
     */
    public static function gorseller(mixed $tanim): array
    {
        $k = [];
        $adimlar = \is_array($tanim) && \is_array($tanim['adimlar'] ?? null) ? $tanim['adimlar'] : [];
        foreach ($adimlar as $adim) {
            if (\is_array($adim) && \is_string($adim['gorsel'] ?? null)) {
                $k[] = $adim['gorsel'];
            }
            foreach (\is_array($adim) && \is_array($adim['secenekler'] ?? null) ? $adim['secenekler'] : [] as $s) {
                if (\is_array($s) && \is_string($s['gorsel'] ?? null)) {
                    $k[] = $s['gorsel'];
                }
            }
        }

        return array_values(array_unique($k));
    }

    /** @param list<string> $h */
    private static function gorsel(mixed $deger, string $yer, array &$h): void
    {
        if (!\is_string($deger) || 1 !== preg_match('/^[0-9a-f]{64}$/', $deger)) {
            $h[] = "{$yer}: görsel kimliği geçersiz";
        }
    }

    /** @param list<string> $h */
    private static function metin(mixed $deger, string $tur, string $yer, array &$h): void
    {
        [$en_az, $en_cok] = self::UZUNLUK[$tur];
        if (!\is_string($deger) || mb_strlen(trim($deger)) < $en_az || mb_strlen($deger) > $en_cok) {
            $h[] = "{$yer}: {$en_az}–{$en_cok} karakter metin olmalı";
        }
    }

    /**
     * @param array<string, int> $sira
     * @param list<string>       $h
     */
    private static function secenekler(mixed $secenekler, array $sira, int $i, string $yer, array &$h): void
    {
        if (!\is_array($secenekler) || !array_is_list($secenekler) || \count($secenekler) < 2 || \count($secenekler) > 8) {
            $h[] = "{$yer}: 2–8 seçenek olmalı";

            return;
        }
        $gorulen = [];
        foreach ($secenekler as $j => $s) {
            $sy = "{$yer} seçenek ".($j + 1);
            $istege = \is_array($s) ? array_values(array_intersect(['gorsel', 'hedef'], array_map(strval(...), array_keys($s)))) : [];
            if (!\is_array($s) || !self::anahtarlar($s, ['id', 'metin', ...$istege])) {
                $h[] = "{$sy}: yalnız id, metin ve (isteğe bağlı) gorsel, hedef";
                continue;
            }
            if (\array_key_exists('hedef', $s)) {
                self::hedef($s['hedef'], $sira, $i, $sy, $h);
            }
            if (\array_key_exists('gorsel', $s)) {
                self::gorsel($s['gorsel'], $sy, $h);
            }
            if (!\is_string($s['id']) || 1 !== preg_match(self::KIMLIK, $s['id']) || isset($gorulen[$s['id']])) {
                $h[] = "{$sy}: kimlik geçersiz ya da tekrar ediyor";
            } else {
                $gorulen[$s['id']] = true;
            }
            self::metin($s['metin'], 'secenek', "{$sy} metin", $h);
        }
    }

    /**
     * H5: atlama hedefi tanımda DAHA SONRAKİ bir adımın kimliği olmalı.
     *
     * @param array<string, int> $sira
     * @param list<string>       $h
     */
    private static function hedef(mixed $hedef, array $sira, int $i, string $yer, array &$h): void
    {
        if (!\is_string($hedef) || !isset($sira[$hedef]) || $sira[$hedef] <= $i) {
            $h[] = "{$yer}: hedef sonraki adımlardan birinin kimliği olmalı (yalnız ileri)";
        }
    }
}
