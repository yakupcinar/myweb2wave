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
export type Yonlendirme = {
    baglac?: "hepsi" | "herhangi";
    kosullar: readonly Kosul[];
    hedef: string;
};

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

/** İlk tutan kuralın hedefi; hiçbiri tutmazsa (ya da kural yoksa) tanımsız. */
export function yonlendir(
    kurallar: readonly Yonlendirme[] | undefined,
    o: Ozellikler,
): string | undefined {
    return kurallar?.find((k) =>
        k.baglac === "herhangi"
            ? k.kosullar.some((c) => kosulTutar(c, o))
            : k.kosullar.every((c) => kosulTutar(c, o)),
    )?.hedef;
}
