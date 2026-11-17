// T3a: koşul motoru (bildirimsel; JS koşulu yok — D1). Girdi T2 özellikleri
// (ozellikler.ts, sunucuyla aynı adlar). Doğrulama çekirdekte (Tanim.php):
// burada gelen kural geçerli sayılır. Yokluk: esit tutmaz, esit_degil tutar;
// icerir tutmaz, icermez tutar.
import type { Ozellikler } from "./ozellikler";

export type Kosul = {
    ozellik: string;
    islem: "esit" | "esit_degil" | "icerir" | "icermez" | "var" | "yok";
    deger?: string | number;
};
export type KosulGrubu = {
    baglac?: "hepsi" | "herhangi";
    kosullar: readonly Kosul[];
};
export type Yonlendirme = KosulGrubu & { hedef: string };

export function kosulTutar(k: Kosul, o: Ozellikler): boolean {
    const v = o[k.ozellik];
    switch (k.islem) {
        case "var":
            return v !== undefined;
        case "yok":
            return v === undefined;
        case "esit":
            return v === k.deger;
        case "esit_degil":
            return v !== k.deger;
        case "icerir":
            return Array.isArray(v) && v.includes(String(k.deger));
        case "icermez":
            return !(Array.isArray(v) && v.includes(String(k.deger)));
    }
}

/** T3b: grup — hepsi (varsayılan) VE, herhangi VEYA. */
export function grupTutar(g: KosulGrubu, o: Ozellikler): boolean {
    return g.baglac === "herhangi"
        ? g.kosullar.some((c) => kosulTutar(c, o))
        : g.kosullar.every((c) => kosulTutar(c, o));
}

/** İlk tutan kuralın hedefi; hiçbiri tutmazsa (ya da kural yoksa) tanımsız. */
export function yonlendir(
    kurallar: readonly Yonlendirme[] | undefined,
    o: Ozellikler,
): string | undefined {
    return kurallar?.find((k) => grupTutar(k, o))?.hedef;
}

export type KosulluDeger = KosulGrubu & { alan: string; deger: string };

/**
 * T3c: koşullu değerleri uygular — alan başına ilk tutan kural; tutmazsa
 * alanın kendi değeri. `degerler` çıktıda yok (bileşen kuralları bilmez).
 */
export function degerleriUygula<
    T extends { degerler?: readonly KosulluDeger[] },
>(nesne: T, o: Ozellikler): Omit<T, "degerler"> {
    if (!nesne.degerler) return nesne;
    const { degerler, ...kalan } = nesne;
    const atanan = new Set<string>();
    const sonuc: Record<string, unknown> = { ...kalan };
    for (const k of degerler)
        if (!atanan.has(k.alan) && grupTutar(k, o)) {
            atanan.add(k.alan);
            sonuc[k.alan] = k.deger;
        }
    return sonuc as Omit<T, "degerler">;
}
