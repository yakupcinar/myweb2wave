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

    /** Ç71: AI'a yön veren örnekler (kopya değil) — her biri yayından geçer (TemaTest). */
    public const AI_ORNEKLERI = [
        'sağlık / sakinlik' => ['anaRenk' => '#0f766e', 'arkaPlan' => '#f0fdf4', 'metinRengi' => '#14532d', 'yaziTipi' => 'yuvarlak', 'kose' => 20],
        'teknoloji / üretkenlik' => ['anaRenk' => '#22d3ee', 'arkaPlan' => '#0b1120', 'metinRengi' => '#e2e8f0', 'yaziTipi' => 'mono', 'kose' => 6],
        'finans / ciddi' => ['anaRenk' => '#1e3a8a', 'arkaPlan' => '#f8fafc', 'metinRengi' => '#0f172a', 'yaziTipi' => 'serif', 'kose' => 4],
        'eğlence / çocuk' => ['anaRenk' => '#db2777', 'arkaPlan' => '#fff7ed', 'metinRengi' => '#431407', 'yaziTipi' => 'yuvarlak', 'kose' => 24],
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
        $var = array_map(strval(...), array_keys($tema));
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
        }

        return $h;
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
