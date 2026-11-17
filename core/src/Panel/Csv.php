<?php

declare(strict_types=1);

namespace App\Panel;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * R2 (A29): panel tablolarının CSV dosyası. UTF-8 BOM (Excel Türkçe
 * karakteri bozmasın) + virgül (RFC 4180). Formül gibi başlayan hücre
 * (= + - @, sekme, satır başı) "'" ile başlar: serbest yanıt ya da kampanya
 * adı tablo programında formül olarak çalışmasın (CSV injection).
 */
final class Csv
{
    /**
     * @param list<string>                               $basliklar
     * @param iterable<list<string|int|float|bool|null>> $satirlar
     */
    public static function yanit(string $dosya, array $basliklar, iterable $satirlar): Response
    {
        $f = fopen('php://temp', 'r+');
        \assert(false !== $f);
        fwrite($f, "\u{FEFF}");
        fputcsv($f, $basliklar, ',', '"', '');
        foreach ($satirlar as $satir) {
            fputcsv($f, array_map(self::hucre(...), $satir), ',', '"', '');
        }
        rewind($f);
        $icerik = (string) stream_get_contents($f);
        fclose($f);

        return new Response($icerik, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $dosya.'-'.date('Y-m-d').'.csv'),
        ]);
    }

    private static function hucre(string|int|float|bool|null $d): string
    {
        if (\is_bool($d)) {
            return $d ? 'evet' : 'hayır';
        }
        $d = (string) $d;

        return '' !== $d && str_contains("=+-@\t\r", $d[0]) ? "'".$d : $d;
    }
}
