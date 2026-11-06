// Sayı biçimi panelin eski Twig çıktısıyla aynı: "33,3 %", payda 0 ise "—".
export function yuzde(pay: number, payda: number, basamak = 1): string {
    if (payda <= 0) return "—";
    return `${((pay / payda) * 100).toFixed(basamak).replace(".", ",")} %`;
}

/** ISO zaman → "2026-10-27 06:31" (UTC; panel saat dilimi göstermez). */
export function zaman(iso: string): string {
    return new Date(iso).toISOString().slice(0, 16).replace("T", " ");
}
