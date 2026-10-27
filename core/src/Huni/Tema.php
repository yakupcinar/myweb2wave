<?php

declare(strict_types=1);

namespace App\Huni;

/**
 * Huni başına görünüm (A8 / E2.2): üç renk, yazı tipi, köşe. Yazı tipleri
 * yalnız CİHAZIN kendi yazı tipleri — dışarıdan font yüklemek ziyaretçinin
 * IP'sini üçüncü tarafa yollar (KVKK/çerez onayı, Ç12). Okunurluk
 * yayından önce ölçülür: metin/arka plan kontrastı WCAG AA (4,5:1).
 * Düğme yazısı huni sayfasında siyah/beyazdan kontrastı yüksek olanı seçilir.
 */
final class Tema
{
    public const VARSAYILAN = [
        'anaRenk' => '#2563eb', 'arkaPlan' => '#ffffff', 'metinRengi' => '#111827',
        'yaziTipi' => 'sistem', 'kose' => 10,
    ];

    /** Seçilebilir yazı tipleri — huni sayfası bunları cihaz yazı tipi yığınına çevirir. */
    public const YAZI_TIPLERI = ['sistem', 'serif', 'yuvarlak', 'mono'];

    public const AZAMI_KOSE = 32;

    /** WCAG AA, normal metin. */
    public const ASGARI_KONTRAST = 4.5;

    /** G3: görsel zeminde karartma katmanının üst sınırı (%100 görseli tamamen örterdi). */
    public const AZAMI_KARARTMA = 95;

    /** Ç71: AI'a yön veren örnekler (kopya değil) — her biri yayından geçer (TemaTest). */
    public const AI_ORNEKLERI = [
        'sağlık / sakinlik' => ['anaRenk' => '#0f766e', 'arkaPlan' => '#f0fdf4', 'metinRengi' => '#14532d', 'yaziTipi' => 'yuvarlak', 'kose' => 20],
        'teknoloji / üretkenlik' => ['anaRenk' => '#22d3ee', 'arkaPlan' => '#0b1120', 'metinRengi' => '#e2e8f0', 'yaziTipi' => 'mono', 'kose' => 6, 'zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#1e293b']],
        'finans / ciddi' => ['anaRenk' => '#1e3a8a', 'arkaPlan' => '#f8fafc', 'metinRengi' => '#0f172a', 'yaziTipi' => 'serif', 'kose' => 4],
        'eğlence / çocuk' => ['anaRenk' => '#db2777', 'arkaPlan' => '#fff7ed', 'metinRengi' => '#431407', 'yaziTipi' => 'yuvarlak', 'kose' => 24, 'zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#fce7f3']],
        'iletişim / sosyal' => ['anaRenk' => '#7c3aed', 'arkaPlan' => '#faf5ff', 'metinRengi' => '#2e1065', 'yaziTipi' => 'sistem', 'kose' => 16],
    ];

    /**
     * Ç71: panel asistanı ve MCP talimatına giren ortak görünüm kuralı —
     * yoksa AI tema koymuyor, her huni varsayılan görünümde çıkıyordu.
     */
    public static function aiYonergesi(): string
    {
        $v = self::VARSAYILAN;
        $ornekler = '';
        foreach (self::AI_ORNEKLERI as $tur => $tema) {
            $ornekler .= "\n- {$tur}: ".json_encode($tema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        }

        return 'Görünüm (tema): taslakta tema yoksa ya da varsayılan görünümse '
            ."(ana renk {$v['anaRenk']}, zemin {$v['arkaPlan']}, yazı tipi {$v['yaziTipi']}), ürün bağlamına (kategori, kitle, ton) uyan bir tema seç ve tanıma ekle — her huni aynı görünümde kalmasın. "
            .'Yöneticinin seçtiği bir tema varsa istenmedikçe dokunma. '
            .'Alanların hepsi zorunlu: anaRenk, arkaPlan, metinRengi (#rrggbb, küçük harf), yaziTipi ('.implode(', ', self::YAZI_TIPLERI).'), kose (0–'.self::AZAMI_KOSE.' tam sayı). '
            .'metinRengi ile arkaPlan arasındaki kontrast en az '.self::ASGARI_KONTRAST.':1 olmalı — düşükse kayıt reddedilir. Düğme yazısının rengini sayfa kendisi seçer. '
            // G3 + G1 ölçümü: ekranın çoğu zemin; yalnız ana renk değişirse huniler aynı görünür.
            .'Görünümü en çok zemin değiştirir: arkaPlan\'ı ürüne göre seç; isteğe bağlı zemin alanı düz rengin yerine geçiş {"tur":"gecis","ikinciRenk":"#rrggbb"} (metin alt renkte de okunmalı) ya da görsel {"tur":"gorsel","gorsel":"<görsel kimliği>","karartma":0–'.self::AZAMI_KARARTMA.'} (yalnız bu uygulamaya yüklenmiş görsel; karartma yetersizse hata en az değeri söyler). '
            .'Örnekler (aynen kopyalama, ürüne uyarla):'.$ornekler
            // Ç72: üretilen görünümü görmeden düzeltemez.
            ."\nKaydettikten sonra ekran_goruntusu ile en az ilk adımı ve ödeme ekranını gör; okunmayan, taşan ya da sıkışık bir şey varsa düzeltip yeniden kaydet.";
    }

    /** @return list<string> */
    public static function hatalar(mixed $tema): array
    {
        if (!\is_array($tema)) {
            return ['tema bir nesne olmalı'];
        }
        $beklenen = array_keys(self::VARSAYILAN);
        // G3: zemin isteğe bağlı; ayrı denetlenir.
        $var = array_values(array_diff(array_map(strval(...), array_keys($tema)), ['zemin']));
        sort($var);
        sort($beklenen);
        if ($var !== $beklenen) {
            return ['tema alanları tam olarak '.implode(', ', array_keys(self::VARSAYILAN)).' olmalı'];
        }
        $h = [];
        foreach (['anaRenk', 'arkaPlan', 'metinRengi'] as $alan) {
            if (!\is_string($tema[$alan]) || 1 !== preg_match('/^#[0-9a-f]{6}$/', $tema[$alan])) {
                $h[] = "tema {$alan}: #rrggbb biçiminde (küçük harf) renk olmalı";
            }
        }
        if (!\in_array($tema['yaziTipi'], self::YAZI_TIPLERI, true)) {
            $h[] = 'tema yaziTipi: '.implode(', ', self::YAZI_TIPLERI).' olmalı';
        }
        if (!\is_int($tema['kose']) || $tema['kose'] < 0 || $tema['kose'] > self::AZAMI_KOSE) {
            $h[] = 'tema kose: 0–'.self::AZAMI_KOSE.' tam sayı olmalı';
        }
        if ([] === $h && \is_string($tema['metinRengi']) && \is_string($tema['arkaPlan'])) {
            $oran = self::kontrast($tema['metinRengi'], $tema['arkaPlan']);
            if ($oran < self::ASGARI_KONTRAST) {
                $h[] = \sprintf('tema: metin arka planda okunmuyor (kontrast %.2f:1, en az %.1f:1)', $oran, self::ASGARI_KONTRAST);
            }
            if ([] === $h && \array_key_exists('zemin', $tema)) {
                $h = self::zeminHatalari($tema['zemin'], $tema['metinRengi'], $tema['arkaPlan']);
            }
        }

        return $h;
    }

    /**
     * G3: geçişte metin alt renkte de okunmalı. Görselde içerik bilinmez:
     * karartma katmanı (arkaPlan rengi, %k) görsel en kötü durumda tam siyah
     * ya da tam beyaz olsa da metni okutmalı — sRGB'de karışım (CSS
     * color-mix in srgb ile aynı).
     *
     * @return list<string>
     */
    private static function zeminHatalari(mixed $zemin, string $metin, string $arka): array
    {
        $tur = \is_array($zemin) ? ($zemin['tur'] ?? null) : null;
        $alanlar = match ($tur) {
            'gecis' => ['tur', 'ikinciRenk'],
            'gorsel' => ['tur', 'gorsel', 'karartma'],
            default => null,
        };
        if (null === $alanlar) {
            return ['tema zemin: tur gecis ya da gorsel olmalı'];
        }
        $var = array_map(strval(...), array_keys($zemin));
        sort($var);
        $istenen = $alanlar;
        sort($istenen);
        if ($var !== $istenen) {
            return ["tema zemin ({$tur}): alanlar tam olarak ".implode(', ', $alanlar).' olmalı'];
        }
        if ('gecis' === $tur) {
            $ikinci = $zemin['ikinciRenk'];
            if (!\is_string($ikinci) || 1 !== preg_match('/^#[0-9a-f]{6}$/', $ikinci)) {
                return ['tema zemin ikinciRenk: #rrggbb biçiminde (küçük harf) renk olmalı'];
            }
            $oran = self::kontrast($metin, $ikinci);

            return $oran < self::ASGARI_KONTRAST
                ? [\sprintf('tema zemin: metin geçişin alt renginde okunmuyor (kontrast %.2f:1, en az %.1f:1)', $oran, self::ASGARI_KONTRAST)]
                : [];
        }
        if (!\is_string($zemin['gorsel']) || 1 !== preg_match('/^[0-9a-f]{64}$/', $zemin['gorsel'])) {
            return ['tema zemin gorsel: görsel kimliği geçersiz'];
        }
        $k = $zemin['karartma'];
        if (!\is_int($k) || $k < 0 || $k > self::AZAMI_KARARTMA) {
            return ['tema zemin karartma: 0–'.self::AZAMI_KARARTMA.' tam sayı olmalı'];
        }
        $okunur = static fn (int $y): bool => min(
            self::kontrast($metin, self::karistir($arka, '#000000', $y)),
            self::kontrast($metin, self::karistir($arka, '#ffffff', $y)),
        ) >= self::ASGARI_KONTRAST;
        if ($okunur($k)) {
            return [];
        }
        $enAz = null;
        for ($y = $k + 1; $y <= self::AZAMI_KARARTMA && null === $enAz; ++$y) {
            $enAz = $okunur($y) ? $y : null;
        }

        return [null === $enAz
            ? 'tema zemin: bu renklerle görsel üstünde metin %'.self::AZAMI_KARARTMA.' karartmada bile okunmuyor — arkaPlan/metinRengi farkını artır'
            : "tema zemin: görsel üstünde metin okunmayabilir — karartma en az %{$enAz} olmalı (şu an %{$k})"];
    }

    /** sRGB'de %$yuzde $a + kalan $b (CSS color-mix(in srgb, a y%, b)); #rrggbb. */
    private static function karistir(string $a, string $b, int $yuzde): string
    {
        $kanal = static fn (int $i): int => (int) round($yuzde / 100 * hexdec(substr($a, $i, 2)) + (1 - $yuzde / 100) * hexdec(substr($b, $i, 2)));

        return \sprintf('#%02x%02x%02x', $kanal(1), $kanal(3), $kanal(5));
    }

    /** WCAG kontrast oranı (1–21). */
    public static function kontrast(string $a, string $b): float
    {
        [$la, $lb] = [self::parlaklik($a), self::parlaklik($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** WCAG göreli parlaklık; $hex #rrggbb. */
    private static function parlaklik(string $hex): float
    {
        $kanal = static function (string $iki): float {
            $c = hexdec($iki) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $kanal(substr($hex, 1, 2)) + 0.7152 * $kanal(substr($hex, 3, 2)) + 0.0722 * $kanal(substr($hex, 5, 2));
    }
}
