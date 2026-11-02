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
        'yazar' => [1, 80], 'unvan' => [0, 100], 'aciklama' => [0, 500],
        'fayda' => [1, 120], 'gorselMetni' => [1, 200], 'madde' => [1, 80],
        // T1: blok metinleri.
        'blokMetni' => [1, 1000], 'satir' => [1, 120], 'deger' => [1, 12], 'kutu' => [1, 500],
    ];

    /**
     * T1: AI istemlerine (panel asistanı + MCP) ortak blok kataloğu yönergesi;
     * uydurma sayı ve yorum yasağı reklam metni için (yanıltıcı reklam).
     */
    public const EKRAN_YONERGESI = 'Tek türe sığmayan ekran için ekran adımı: baslik (sayfanın başlığı) + bloklar (0–12, yukarıdan aşağı) + dugme, isteğe bağlı hedef. '
        .'Bloklar: baslik (ara başlık), metin (paragraf), gorsel (yüklenmiş görsel, gorselMetni), maddeler (1–6), yorum (metin, yazar, isteğe bağlı unvan ve puan 1–5), istatistik (deger ≤ 12 karakter + metin), kutu (vurgulu not, isteğe bağlı tek emoji simge), soru (ekranda en çok bir; soru adımının secimTuru/secenekler/duzen/yanitZorunlu alanları, soru metni ekranın başlığı — tekli seçimde ekrana dugme ve hedef yazma, hedef seçenekte; coklu/metin seçimde dugme gönderir). '
        .'Açıklama, görsel ya da güvence notu gereken soruyu soru bloklu ekranla kur. '
        .'Neden bu ürün, nasıl çalışır, güvence gibi ekranları tanıtım yerine bloklarla kur; tüm huniyi tek tip adımla doldurma. '
        .'istatistik bloğuna yalnız ürün bağlamında verilmiş gerçek bir sayı yaz — yoksa istatistik bloğu koyma. '
        .'yorum bloğu da yorum adımı gibidir: gerçek yorum yoksa unvana "Örnek — yayından önce gerçeğiyle değiştir" yaz.';

    /** T1c: tam ekran kapaklı ekranda izinli bloklar (yüzeysiz, metin). */
    public const TAM_EKRAN_BLOKLARI = ['baslik', 'metin', 'maddeler', 'istatistik'];

    /** T1: ekran adımındaki blok sayısı. */
    public const AZAMI_BLOK = 12;

    /**
     * T1 blok kataloğu: tür → [zorunlu alanlar, isteğe bağlı alanlar]; alan →
     * UZUNLUK anahtarı, null = kendi denetimi (görsel, liste, puan, simge).
     *
     * @var array<string, array{array<string, ?string>, array<string, ?string>}>
     */
    public const BLOKLAR = [
        'baslik' => [['metin' => 'baslik'], []],
        'metin' => [['metin' => 'blokMetni'], []],
        'gorsel' => [['gorsel' => null], ['gorselMetni' => 'gorselMetni']],
        'maddeler' => [['maddeler' => null], []],
        'yorum' => [['metin' => 'blokMetni', 'yazar' => 'yazar'], ['unvan' => 'unvan', 'puan' => null]],
        'istatistik' => [['deger' => 'deger', 'metin' => 'satir'], []],
        'kutu' => [['metin' => 'kutu'], ['simge' => null]],
        // T1b: secenekler serbest yanıtta yok, seçmelide zorunlu (aşağıda).
        'soru' => [[], ['secimTuru' => null, 'duzen' => null, 'yanitZorunlu' => null, 'secenekler' => null]],
    ];

    /** G5: tanıtım adımında görselin yeri. */
    public const DUZENLER = ['ust', 'kahraman', 'tamEkran'];

    /** G4: soru adımında seçeneklerin dizilişi. */
    public const SORU_DUZENLERI = ['liste', 'izgara'];

    /** Tür → zorunlu metin alanları (başka alan kabul edilmez). */
    private const TURLER = [
        'tanitim' => ['baslik', 'metin', 'dugme'],
        'soru' => ['soru'],
        'yorum' => ['baslik', 'metin', 'yazar', 'unvan', 'dugme'],
        // Mobil görünüm 4: düğmesiz; maddeler ve süre isteğe bağlı.
        'yukleme' => ['baslik'],
        // T1: başlık (h1) + bloklar; düğme ve hedef aşağıda (T1b: tek seçimli
        // soru bloğunda düğme yok, seçenek geçirir).
        'ekran' => ['baslik'],
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
        // logo isteğe bağlı (Ç61 (5)): temadan bağımsız, görsel kimliği.
        $istege = \is_array($tanim) ? array_values(array_intersect(['tema', 'logo'], array_map(strval(...), array_keys($tanim)))) : [];
        if (!\is_array($tanim) || !self::anahtarlar($tanim, ['sayfaBasligi', 'adimlar', ...$istege])) {
            return ['tanım yalnız sayfaBasligi, adimlar ve (isteğe bağlı) tema, logo içermeli'];
        }
        $h = \array_key_exists('tema', $tanim) ? Tema::hatalar($tanim['tema']) : [];
        if (\array_key_exists('logo', $tanim)) {
            self::gorsel($tanim['logo'], 'logo', $h);
        }
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
                $h[] = "{$yer}: tür tanitim, soru, yorum, yukleme, ekran ya da paywall olmalı";
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
                'soru' => ('metin' === ($adim['secimTuru'] ?? 'tekli')) ? [] : ['secenekler'], 'paywall' => ['plan'], 'ekran' => ['bloklar'], default => [],
            }];
            if (\array_key_exists('ilerlemeGoster', $adim)) {
                $beklenen[] = 'ilerlemeGoster';
                if (!\is_bool($adim['ilerlemeGoster'])) {
                    $h[] = "{$yer}: ilerlemeGoster true ya da false olmalı";
                }
            }
            if ('soru' === $tur && \array_key_exists('secimTuru', $adim)) {
                $beklenen[] = 'secimTuru';
                if (!\in_array($adim['secimTuru'], ['tekli', 'coklu', 'metin'], true)) {
                    $h[] = "{$yer}: secimTuru tekli, coklu ya da metin olmalı";
                }
            }
            $metinYaniti = 'metin' === ($adim['secimTuru'] ?? 'tekli');
            if ($metinYaniti && \array_key_exists('yanitZorunlu', $adim)) {
                $beklenen[] = 'yanitZorunlu';
                if (!\is_bool($adim['yanitZorunlu'])) {
                    $h[] = "{$yer}: yanitZorunlu true ya da false olmalı";
                }
            }
            if (!$metinYaniti && \array_key_exists('yanitZorunlu', $adim)) {
                $h[] = "{$yer}: yanitZorunlu yalnız serbest yanıt türünde kullanılabilir";
            }
            if ('soru' === $tur && \array_key_exists('aciklama', $adim)) {
                $beklenen[] = 'aciklama';
                self::metin($adim['aciklama'], 'aciklama', "{$yer} aciklama", $h);
            }
            if ('soru' === $tur && \array_key_exists('devamDugmesi', $adim)) {
                $beklenen[] = 'devamDugmesi';
                self::metin($adim['devamDugmesi'], 'dugme', "{$yer} devamDugmesi", $h);
            }
            // E2.3: tanıtım adımında isteğe bağlı görsel; T1c: ekranın kapağı da.
            $kapakli = \in_array($tur, ['tanitim', 'ekran'], true);
            if ($kapakli && \array_key_exists('gorsel', $adim)) {
                $beklenen[] = 'gorsel';
                self::gorsel($adim['gorsel'], $yer, $h);
            }
            if ($kapakli && \array_key_exists('gorselMetni', $adim)) {
                $beklenen[] = 'gorselMetni';
                self::gorselMetni($adim, $yer, $h);
            }
            // G4: seçeneklerin dizilişi — serbest yanıtta seçenek yok.
            if ('soru' === $tur && \array_key_exists('duzen', $adim)) {
                $beklenen[] = 'duzen';
                self::soruDuzeni($adim['duzen'], $metinYaniti, $yer, $h);
            }
            // Mobil görünüm 4: yükleme adımının maddeleri ve süresi.
            if ('yukleme' === $tur && \array_key_exists('maddeler', $adim)) {
                $beklenen[] = 'maddeler';
                self::maddeler($adim['maddeler'], $yer, $h);
            }
            if ('yukleme' === $tur && \array_key_exists('sure', $adim)) {
                $beklenen[] = 'sure';
                if (!\is_int($adim['sure']) || $adim['sure'] < 2 || $adim['sure'] > 10) {
                    $h[] = "{$yer}: sure: 2–10 tam sayı (saniye) olmalı";
                }
            }
            // G5: görselin yeri; kahraman/tamEkran görselsiz anlamsız.
            if ($kapakli && \array_key_exists('duzen', $adim)) {
                $beklenen[] = 'duzen';
                if (!\in_array($adim['duzen'], self::DUZENLER, true)) {
                    $h[] = "{$yer}: duzen: ".implode(', ', self::DUZENLER).' olmalı';
                } elseif ('ust' !== $adim['duzen'] && !\array_key_exists('gorsel', $adim)) {
                    $h[] = "{$yer}: duzen {$adim['duzen']} görsel ister";
                }
            }
            // H5: tanıtım düğmesi de atlayabilir — dallar ortak adımda birleşsin.
            if (\in_array($tur, ['tanitim', 'ekran'], true) && \array_key_exists('hedef', $adim)) {
                $beklenen[] = 'hedef';
                self::hedef($adim['hedef'], $sira, $i, $yer, $h);
            }
            // Ç61 (3): paywall'da isteğe bağlı fayda listesi ve yasal bağlantılar.
            if ('paywall' === $tur && \array_key_exists('faydalar', $adim)) {
                $beklenen[] = 'faydalar';
                self::faydalar($adim['faydalar'], $yer, $h);
            }
            foreach ('paywall' === $tur ? ['sartlar', 'gizlilik', 'iptal'] : [] as $alan) {
                if (\array_key_exists($alan, $adim)) {
                    $beklenen[] = $alan;
                    self::adres($adim[$alan], "{$yer} {$alan}", $h);
                }
            }
            // T1b: tek seçimli soru bloğunda seçenek geçirir — düğme ve ekran hedefi yok.
            $tekSecimli = 'ekran' === $tur && self::tekSecimliSoru($adim['bloklar'] ?? null);
            if ('ekran' === $tur && \array_key_exists('dugme', $adim)) {
                $beklenen[] = 'dugme';
                if ($tekSecimli) {
                    $h[] = "{$yer}: tek seçimli soru bloğu olan ekranda dugme olmaz (seçenek seçilince geçer)";
                } else {
                    self::metin($adim['dugme'], 'dugme', "{$yer} dugme", $h);
                }
            } elseif ('ekran' === $tur && !$tekSecimli) {
                $h[] = "{$yer}: dugme olmalı";
            }
            if ($tekSecimli && \array_key_exists('hedef', $adim)) {
                $h[] = "{$yer}: tek seçimli soru bloğunda hedef seçeneklerde verilir";
            }
            if (!self::anahtarlar($adim, $beklenen)) {
                $h[] = "{$yer}: alanlar tam olarak ".implode(', ', $beklenen).' olmalı';
                continue;
            }
            foreach (self::TURLER[$tur] as $alan) {
                self::metin($adim[$alan], $alan, "{$yer} {$alan}", $h);
            }
            if ('ekran' === $tur) {
                self::bloklar($adim['bloklar'], $sira, $i, $yer, $h, 'tamEkran' === ($adim['duzen'] ?? null));
            }
            if ('soru' === $tur && \array_key_exists('secenekler', $adim)) {
                self::secenekler($adim['secenekler'], $sira, $i, $yer, $h, 'coklu' === ($adim['secimTuru'] ?? 'tekli'));
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
        // Ç61 (5): logo da bir görsel — aynı "bu uygulamada var mı" kuralı.
        $k = \is_array($tanim) && \is_string($tanim['logo'] ?? null) ? [$tanim['logo']] : [];
        // G3: zemin görseli de.
        $zemin = \is_array($tanim) && \is_array($tanim['tema'] ?? null) && \is_array($tanim['tema']['zemin'] ?? null) ? $tanim['tema']['zemin'] : [];
        if ('gorsel' === ($zemin['tur'] ?? null) && \is_string($zemin['gorsel'] ?? null)) {
            $k[] = $zemin['gorsel'];
        }
        $adimlar = \is_array($tanim) && \is_array($tanim['adimlar'] ?? null) ? $tanim['adimlar'] : [];
        foreach ($adimlar as $adim) {
            if (\is_array($adim) && \is_string($adim['gorsel'] ?? null)) {
                $k[] = $adim['gorsel'];
            }
            // T1: görsel bloğu.
            foreach (\is_array($adim) && \is_array($adim['bloklar'] ?? null) ? $adim['bloklar'] : [] as $b) {
                if (\is_array($b) && \is_string($b['gorsel'] ?? null)) {
                    $k[] = $b['gorsel'];
                }
                // T1b: soru bloğunun seçenek görselleri.
                foreach (\is_array($b) && \is_array($b['secenekler'] ?? null) ? $b['secenekler'] : [] as $s) {
                    if (\is_array($s) && \is_string($s['gorsel'] ?? null)) {
                        $k[] = $s['gorsel'];
                    }
                }
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

    /**
     * Ç44: alt metin yalnız görselle — görselsiz alt metin ekranda hiçbir
     * yere çıkmaz, yazanı yanıltır.
     *
     * @param array<mixed> $nesne
     * @param list<string> $h
     */
    private static function gorselMetni(array $nesne, string $yer, array &$h): void
    {
        if (!\array_key_exists('gorsel', $nesne)) {
            $h[] = "{$yer}: gorselMetni yalnız gorsel ile kullanılabilir";

            return;
        }
        self::metin($nesne['gorselMetni'], 'gorselMetni', "{$yer} gorselMetni", $h);
    }

    /** @param list<string> $h */
    private static function maddeler(mixed $maddeler, string $yer, array &$h): void
    {
        if (!\is_array($maddeler) || !array_is_list($maddeler) || \count($maddeler) > 5) {
            $h[] = "{$yer}: maddeler: en çok 5 metinlik liste olmalı";

            return;
        }
        foreach ($maddeler as $j => $m) {
            self::metin($m, 'madde', "{$yer} madde ".($j + 1), $h);
        }
    }

    /**
     * T1: ekranın blokları — her blok katalogdaki bir tür, alanları tam olarak
     * o türün alanları. Sayfanın h1'i adımın baslik alanı; baslik bloğu ara başlık.
     *
     * @param array<string, int> $sira
     * @param list<string>       $h
     */
    private static function bloklar(mixed $bloklar, array $sira, int $i, string $yer, array &$h, bool $tamEkran = false): void
    {
        // T1c: bloksuz ekran geçerli (başlık + düğme).
        if (!\is_array($bloklar) || !array_is_list($bloklar) || \count($bloklar) > self::AZAMI_BLOK) {
            $h[] = "{$yer}: bloklar 0–".self::AZAMI_BLOK.' öğeli liste olmalı';

            return;
        }
        $sorular = 0;
        foreach ($bloklar as $j => $b) {
            $by = "{$yer} blok ".($j + 1);
            $tur = \is_array($b) ? ($b['tur'] ?? null) : null;
            if (!\is_array($b) || !\is_string($tur) || !isset(self::BLOKLAR[$tur])) {
                $h[] = "{$by}: tür baslik, metin, gorsel, maddeler, yorum, istatistik, kutu ya da soru olmalı";
                continue;
            }
            // T1c: tam ekranda metin beyaz, karartmanın üstünde — yüzeyli blok
            // (kutu, yorum kartı, seçenek, görsel) beyaz metni açık zeminde bırakırdı.
            if ($tamEkran && !\in_array($tur, self::TAM_EKRAN_BLOKLARI, true)) {
                $h[] = "{$by}: tam ekranda yalnız ".implode(', ', self::TAM_EKRAN_BLOKLARI).' blokları olabilir';
            }
            // T1b: cevap ekranın kimliğiyle kaydedilir — ekranda tek soru.
            if ('soru' === $tur && ++$sorular > 1) {
                $h[] = "{$yer}: ekranda en çok bir soru bloğu olabilir";
            }
            [$zorunlu, $istege] = self::BLOKLAR[$tur];
            $metinYaniti = 'soru' === $tur && 'metin' === ($b['secimTuru'] ?? 'tekli');
            if ('soru' === $tur && !$metinYaniti) {
                $zorunlu = ['secenekler' => null];
            }
            $alanlar = $zorunlu + array_intersect_key($istege, $b);
            if ($metinYaniti) {
                unset($alanlar['secenekler']);
            }
            if (!self::anahtarlar($b, ['tur', ...array_keys($alanlar)])) {
                $h[] = "{$by}: alanlar tam olarak ".implode(', ', ['tur', ...array_keys($zorunlu)]).([] === $istege ? '' : ' ve (isteğe bağlı) '.implode(', ', array_keys($istege))).' olmalı';
                continue;
            }
            foreach ($alanlar as $alan => $uzunluk) {
                if (null !== $uzunluk) {
                    self::metin($b[$alan], $uzunluk, "{$by} {$alan}", $h);
                }
            }
            match ($tur) {
                'gorsel' => self::gorsel($b['gorsel'], $by, $h),
                'maddeler' => self::blokMaddeleri($b['maddeler'], $by, $h),
                'soru' => self::soruBlogu($b, $metinYaniti, $sira, $i, $by, $h),
                default => null,
            };
            if (\array_key_exists('puan', $b) && (!\is_int($b['puan']) || $b['puan'] < 1 || $b['puan'] > 5)) {
                $h[] = "{$by}: puan 1–5 tam sayı olmalı";
            }
            if (\array_key_exists('simge', $b) && !self::emoji($b['simge'])) {
                $h[] = "{$by}: simge tek emoji olmalı";
            }
        }
    }

    /**
     * T1b: soru bloğu — soru adımının yanıt kuralları (metni ekranın başlığı ve
     * blokları taşır).
     *
     * @param array<mixed>       $b
     * @param array<string, int> $sira
     * @param list<string>       $h
     */
    private static function soruBlogu(array $b, bool $metinYaniti, array $sira, int $i, string $yer, array &$h): void
    {
        if (\array_key_exists('secimTuru', $b) && !\in_array($b['secimTuru'], ['tekli', 'coklu', 'metin'], true)) {
            $h[] = "{$yer}: secimTuru tekli, coklu ya da metin olmalı";
        }
        if (\array_key_exists('yanitZorunlu', $b)) {
            if (!$metinYaniti) {
                $h[] = "{$yer}: yanitZorunlu yalnız serbest yanıt türünde kullanılabilir";
            } elseif (!\is_bool($b['yanitZorunlu'])) {
                $h[] = "{$yer}: yanitZorunlu true ya da false olmalı";
            }
        }
        if (\array_key_exists('duzen', $b)) {
            self::soruDuzeni($b['duzen'], $metinYaniti, $yer, $h);
        }
        if (\array_key_exists('secenekler', $b)) {
            self::secenekler($b['secenekler'], $sira, $i, $yer, $h, 'coklu' === ($b['secimTuru'] ?? 'tekli'));
        }
    }

    /** T1b: ekranda tek seçimli soru bloğu var mı (düğme yerine seçenek geçirir). */
    public static function tekSecimliSoru(mixed $bloklar): bool
    {
        foreach (\is_array($bloklar) ? $bloklar : [] as $b) {
            if (\is_array($b) && 'soru' === ($b['tur'] ?? null) && 'tekli' === ($b['secimTuru'] ?? 'tekli')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $h */
    private static function blokMaddeleri(mixed $maddeler, string $yer, array &$h): void
    {
        if (!\is_array($maddeler) || !array_is_list($maddeler) || [] === $maddeler || \count($maddeler) > 6) {
            $h[] = "{$yer}: maddeler 1–6 öğeli liste olmalı";

            return;
        }
        foreach ($maddeler as $j => $m) {
            self::metin($m, 'satir', "{$yer} madde ".($j + 1), $h);
        }
    }

    /** Mobil görünüm 3: tek emoji (ZWJ aile, bayrak, ten tonu tek grafem). */
    private static function emoji(mixed $simge): bool
    {
        return \is_string($simge) && \strlen($simge) <= 32 && 1 === grapheme_strlen($simge) && 1 === preg_match('/\p{Extended_Pictographic}|\p{Regional_Indicator}/u', $simge);
    }

    /** @param list<string> $h */
    private static function soruDuzeni(mixed $duzen, bool $metinYaniti, string $yer, array &$h): void
    {
        if ($metinYaniti) {
            $h[] = "{$yer}: duzen yalnız seçmeli soruda kullanılabilir";
        } elseif (!\in_array($duzen, self::SORU_DUZENLERI, true)) {
            $h[] = "{$yer}: duzen: ".implode(', ', self::SORU_DUZENLERI).' olmalı';
        }
    }

    /** @param list<string> $h */
    private static function faydalar(mixed $deger, string $yer, array &$h): void
    {
        if (!\is_array($deger) || !array_is_list($deger) || [] === $deger || \count($deger) > 6) {
            $h[] = "{$yer}: faydalar 1–6 öğeli liste olmalı";

            return;
        }
        foreach ($deger as $j => $fayda) {
            self::metin($fayda, 'fayda', "{$yer} fayda ".($j + 1), $h);
        }
    }

    /**
     * Yasal bağlantı yalnız https: javascript:/data: gibi şemalar huni
     * sayfasında tıklanınca betik çalıştırırdı (Ç61 (3)).
     *
     * @param list<string> $h
     */
    private static function adres(mixed $deger, string $yer, array &$h): void
    {
        if (!\is_string($deger) || 1 !== preg_match('~^https://[^\s]{1,490}$~', $deger)) {
            $h[] = "{$yer}: https:// ile başlayan adres olmalı (en çok 500 karakter)";
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
    private static function secenekler(mixed $secenekler, array $sira, int $i, string $yer, array &$h, bool $coklu): void
    {
        if (!\is_array($secenekler) || !array_is_list($secenekler) || \count($secenekler) < 2 || \count($secenekler) > 8) {
            $h[] = "{$yer}: 2–8 seçenek olmalı";

            return;
        }
        $gorulen = [];
        foreach ($secenekler as $j => $s) {
            $sy = "{$yer} seçenek ".($j + 1);
            $istege = \is_array($s) ? array_values(array_intersect(['simge', 'gorsel', 'gorselMetni', 'hedef'], array_map(strval(...), array_keys($s)))) : [];
            if (!\is_array($s) || !self::anahtarlar($s, ['id', 'metin', ...$istege])) {
                $h[] = "{$sy}: yalnız id, metin ve (isteğe bağlı) simge, gorsel, gorselMetni, hedef";
                continue;
            }
            // Mobil görünüm 3: tek emoji (ZWJ aile, bayrak, ten tonu tek grafem);
            // görselle birlikte değil — ikisi aynı yeri doldurur.
            if (\array_key_exists('simge', $s)) {
                if (!self::emoji($s['simge'])) {
                    $h[] = "{$sy}: simge tek emoji olmalı";
                } elseif (\array_key_exists('gorsel', $s)) {
                    $h[] = "{$sy}: simge ve gorsel birlikte olmaz";
                }
            }
            if (\array_key_exists('hedef', $s)) {
                if ($coklu) {
                    $h[] = "{$sy}: çoklu seçimde seçenek hedefi kullanılamaz";
                }
                self::hedef($s['hedef'], $sira, $i, $sy, $h);
            }
            if (\array_key_exists('gorsel', $s)) {
                self::gorsel($s['gorsel'], $sy, $h);
            }
            if (\array_key_exists('gorselMetni', $s)) {
                self::gorselMetni($s, $sy, $h);
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
