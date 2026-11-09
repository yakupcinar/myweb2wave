// Sayı biçimi panelin eski Twig çıktısıyla aynı: "33,3 %", payda 0 ise "—".
export function yuzde(pay: number, payda: number, basamak = 1): string {
    if (payda <= 0) return "—";
    return `${((pay / payda) * 100).toFixed(basamak).replace(".", ",")} %`;
}

/** ISO zaman → "2026-10-27 06:31" (UTC; panel saat dilimi göstermez). */
export function zaman(iso: string): string {
    return new Date(iso).toISOString().slice(0, 16).replace("T", " ");
}

/**
 * M5c: para birimi başına tutar listesi → "9,99 € · ₺20,00"; boşsa "—".
 * Çevrilmez — farklı para birimleri toplanmaz.
 */
export function tutarlar(
    liste: readonly { paraBirimi: string; tutar: number }[],
): string {
    if (!liste.length) return "—";
    return liste
        .map((t) =>
            new Intl.NumberFormat("tr-TR", {
                style: "currency",
                currency: t.paraBirimi,
            }).format(t.tutar),
        )
        .join(" · ");
}
