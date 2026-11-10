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

// P7a: Türkçe kısa tarih ve ekli tarih — "3 Kas", "3 Kasım'dan beri",
// "1 Aralık'a kadar", "15 Kasım'da biter". Ek, ünlü uyumuyla ay adına (ya da
// başka yıldaysa yıla) göre seçilir; zaman UTC (panelin geri kalanı gibi).
type Ek = "den" | "e" | "de";
// ad, kısa ad, [-den, -e, -de]
const AYLAR: [string, string, [string, string, string]][] = [
    ["Ocak", "Oca", ["tan", "a", "ta"]],
    ["Şubat", "Şub", ["tan", "a", "ta"]],
    ["Mart", "Mar", ["tan", "a", "ta"]],
    ["Nisan", "Nis", ["dan", "a", "da"]],
    ["Mayıs", "May", ["tan", "a", "ta"]],
    ["Haziran", "Haz", ["dan", "a", "da"]],
    ["Temmuz", "Tem", ["dan", "a", "da"]],
    ["Ağustos", "Ağu", ["tan", "a", "ta"]],
    ["Eylül", "Eyl", ["den", "e", "de"]],
    ["Ekim", "Eki", ["den", "e", "de"]],
    ["Kasım", "Kas", ["dan", "a", "da"]],
    ["Aralık", "Ara", ["tan", "a", "ta"]],
];
// Yılın son basamağının okunuşuna göre (2020 "yirmi" — 2030'a dek doğru).
const YIL_EKI: [string, string, string][] = [
    ["den", "ye", "de"],
    ["den", "e", "de"],
    ["den", "ye", "de"],
    ["ten", "e", "te"],
    ["ten", "e", "te"],
    ["ten", "e", "te"],
    ["dan", "ya", "da"],
    ["den", "ye", "de"],
    ["den", "e", "de"],
    ["dan", "a", "da"],
];
const SIRA: Record<Ek, number> = { den: 0, e: 1, de: 2 };

/** "3 Kas" (başka yıldaysa "3 Kas 2025"). */
export function kisaTarih(iso: string, simdi = new Date()): string {
    const t = new Date(iso);
    const yil = t.getUTCFullYear();
    const ay = AYLAR[t.getUTCMonth()]?.[1] ?? "";
    return `${t.getUTCDate()} ${ay}${yil === simdi.getUTCFullYear() ? "" : ` ${yil}`}`;
}

/** "3 Kasım" (başka yıldaysa "3 Kasım 2025"). */
export function uzunTarih(iso: string, simdi = new Date()): string {
    const t = new Date(iso);
    const yil = t.getUTCFullYear();
    const ad = AYLAR[t.getUTCMonth()]?.[0] ?? "";
    return `${t.getUTCDate()} ${ad}${yil === simdi.getUTCFullYear() ? "" : ` ${yil}`}`;
}

/** "3 Kasım'dan", "1 Aralık'a", "15 Kasım'da" (başka yıldaysa "… 2025'ten"). */
export function ekliTarih(iso: string, ek: Ek, simdi = new Date()): string {
    const t = new Date(iso);
    const yil = t.getUTCFullYear();
    const ekler =
        yil === simdi.getUTCFullYear()
            ? AYLAR[t.getUTCMonth()]?.[2]
            : YIL_EKI[yil % 10];
    return `${uzunTarih(iso, simdi)}'${ekler?.[SIRA[ek]] ?? ""}`;
}
