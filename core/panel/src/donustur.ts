// Huni tanımı (çekirdeğin biçimi, contract HuniTanimi) ↔ Puck verisi.
// Çekirdek yalnız tanımı bilir; Puck yalnız düzenleyicide. Çeviri KAYIPSIZ
// olmalı: tanım → Puck → tanım aynısını vermeli (donustur.test.ts ölçer).
// Puck her bileşene kendi `id`'sini verir; bizim adım kimliğimiz `kimlik`.
import type { Data } from "@puckeditor/core";
import type { components } from "./sozlesme";

export type Tanim = components["schemas"]["HuniTanimi"];
export type Tema = components["schemas"]["HuniTemasi"];
type Adim = Tanim["adimlar"][number];
type Plan = components["schemas"]["PaywallAdimi"]["plan"];
type YaziTipi = Tema["yaziTipi"];

export type SecenekProps = {
    kimlik: string;
    metin: string;
    gorsel: string;
    /** Ç44: boş = dekoratif; görselsizse tanıma yazılmaz. */
    gorselMetni: string;
    hedef: string;
};
export type TanitimProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    dugme: string;
    gorsel: string;
    gorselMetni: string;
    hedef: string;
    ilerlemeGoster: boolean;
};
export type SoruProps = {
    kimlik: string;
    soru: string;
    aciklama: string;
    secenekler: SecenekProps[];
    secimTuru: "tekli" | "coklu" | "metin";
    yanitZorunlu: "istege_bagli" | "zorunlu";
    devamDugmesi: string;
    ilerlemeGoster: boolean;
};
export type PaywallProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    dugme: string;
    plan: Plan;
    ilerlemeGoster: boolean;
    /** Ç61 (3): boş metin / boş adres tanıma yazılmaz. */
    faydalar: { metin: string }[];
    sartlar: string;
    gizlilik: string;
    iptal: string;
};
export type YorumProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    yazar: string;
    unvan: string;
    dugme: string;
    ilerlemeGoster: boolean;
};
export type KokProps = {
    sayfaBasligi: string;
    /** Ç61 (5): boş dize = logo yok. */
    logo: string;
    ozelTema: "evet" | "hayir";
    anaRenk: string;
    arkaPlan: string;
    metinRengi: string;
    yaziTipi: YaziTipi;
    kose: number;
};
export type Bilesenler = {
    Tanitim: TanitimProps;
    TekSecim: SoruProps;
    CokluSecim: SoruProps;
    SerbestYanit: SoruProps;
    Yorum: YorumProps;
    Paywall: PaywallProps;
};
export type HuniVerisi = Data<Bilesenler, KokProps>;

/** Yanıt biçimi → Puck bloğu (Ç61): kayıt tek `soru` şeması, düzenleyicide üç tür. */
const SORU_TURU = {
    tekli: "TekSecim",
    coklu: "CokluSecim",
    metin: "SerbestYanit",
} as const;

/**
 * Boş dize = görsel yok (Puck alanı boş değer taşır; tanımda alan hiç olmaz).
 * Ç44: alt metin yalnız görselle — görselsiz alt metni çekirdek reddeder.
 */
const gorselli = <T extends object>(
    nesne: T,
    gorsel: string,
    gorselMetni = "",
): T & { gorsel?: string; gorselMetni?: string } =>
    gorsel
        ? { ...nesne, gorsel, ...(gorselMetni ? { gorselMetni } : {}) }
        : nesne;
/** H5: boş dize = sıradaki adım (tanımda alan hiç olmaz). */
const hedefli = <T extends object>(
    nesne: T,
    hedef: string,
): T & { hedef?: string } => (hedef ? { ...nesne, hedef } : nesne);

export function tanimdanPuck(tanim: Tanim, varsayilanTema: Tema): HuniVerisi {
    const tema = tanim.tema ?? varsayilanTema;
    return {
        root: {
            props: {
                sayfaBasligi: tanim.sayfaBasligi,
                logo: tanim.logo ?? "",
                ozelTema: tanim.tema ? "evet" : "hayir",
                anaRenk: tema.anaRenk,
                arkaPlan: tema.arkaPlan,
                metinRengi: tema.metinRengi,
                yaziTipi: tema.yaziTipi,
                kose: tema.kose,
            },
        },
        content: tanim.adimlar.map((adim: Adim, i: number) => {
            const id = `${adim.tur}-${i}-${adim.id}`;
            switch (adim.tur) {
                case "tanitim":
                    return {
                        type: "Tanitim" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            baslik: adim.baslik,
                            metin: adim.metin,
                            dugme: adim.dugme,
                            gorsel: adim.gorsel ?? "",
                            gorselMetni: adim.gorselMetni ?? "",
                            hedef: adim.hedef ?? "",
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                        },
                    };
                case "soru":
                    return {
                        // Ç61: menüdeki hazır türle açılır (genel "Soru" yok).
                        type: SORU_TURU[adim.secimTuru ?? "tekli"],
                        props: {
                            id,
                            kimlik: adim.id,
                            soru: adim.soru,
                            aciklama: adim.aciklama ?? "",
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                            secimTuru: adim.secimTuru ?? "tekli",
                            yanitZorunlu: adim.yanitZorunlu
                                ? "zorunlu"
                                : "istege_bagli",
                            devamDugmesi: adim.devamDugmesi ?? "Devam",
                            secenekler: (adim.secenekler ?? []).map((s) => ({
                                kimlik: s.id,
                                metin: s.metin,
                                gorsel: s.gorsel ?? "",
                                gorselMetni: s.gorselMetni ?? "",
                                hedef: s.hedef ?? "",
                            })),
                        },
                    };
                case "yorum":
                    return {
                        type: "Yorum" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            baslik: adim.baslik,
                            metin: adim.metin,
                            yazar: adim.yazar,
                            unvan: adim.unvan,
                            dugme: adim.dugme,
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                        },
                    };
                default:
                    return {
                        type: "Paywall" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            baslik: adim.baslik,
                            metin: adim.metin,
                            dugme: adim.dugme,
                            plan: adim.plan,
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                            faydalar: (adim.faydalar ?? []).map((metin) => ({
                                metin,
                            })),
                            sartlar: adim.sartlar ?? "",
                            gizlilik: adim.gizlilik ?? "",
                            iptal: adim.iptal ?? "",
                        },
                    };
            }
        }),
    };
}

/** Ç61 (3): yalnız dolu faydalar ve adresler — boş liste/adres çekirdekte geçersiz. */
function yasal(p: PaywallProps) {
    const faydalar = (p.faydalar ?? []).map((f) => f.metin).filter(Boolean);
    return {
        ...(faydalar.length ? { faydalar } : {}),
        ...(p.sartlar ? { sartlar: p.sartlar } : {}),
        ...(p.gizlilik ? { gizlilik: p.gizlilik } : {}),
        ...(p.iptal ? { iptal: p.iptal } : {}),
    };
}

export function puckTanim(veri: HuniVerisi): Tanim {
    const k = veri.root.props;
    const adimlar = veri.content.map((oge): Adim => {
        switch (oge.type) {
            case "Tanitim": {
                const p = oge.props as TanitimProps;
                return hedefli(
                    gorselli(
                        {
                            id: p.kimlik,
                            tur: "tanitim" as const,
                            baslik: p.baslik,
                            metin: p.metin,
                            dugme: p.dugme,
                            ...(p.ilerlemeGoster === false
                                ? { ilerlemeGoster: false }
                                : {}),
                        },
                        p.gorsel,
                        p.gorselMetni ?? "",
                    ),
                    p.hedef ?? "",
                );
            }
            case "TekSecim":
            case "CokluSecim":
            case "SerbestYanit": {
                const p = oge.props as SoruProps;
                return {
                    id: p.kimlik,
                    tur: "soru",
                    soru: p.soru,
                    ...(p.aciklama ? { aciklama: p.aciklama } : {}),
                    ...(p.ilerlemeGoster === false
                        ? { ilerlemeGoster: false }
                        : {}),
                    ...(p.secimTuru === "coklu"
                        ? { secimTuru: "coklu" as const }
                        : {}),
                    ...(p.secimTuru === "metin"
                        ? {
                              secimTuru: "metin" as const,
                              ...(p.yanitZorunlu === "zorunlu"
                                  ? { yanitZorunlu: true }
                                  : {}),
                          }
                        : {}),
                    ...(p.devamDugmesi !== "Devam"
                        ? { devamDugmesi: p.devamDugmesi }
                        : {}),
                    ...(p.secimTuru === "metin"
                        ? {}
                        : {
                              secenekler: (p.secenekler ?? []).map((s) => {
                                  const secenek = gorselli(
                                      { id: s.kimlik, metin: s.metin },
                                      s.gorsel ?? "",
                                      s.gorselMetni ?? "",
                                  );
                                  return p.secimTuru === "coklu"
                                      ? secenek
                                      : hedefli(secenek, s.hedef ?? "");
                              }),
                          }),
                };
            }
            case "Yorum": {
                const p = oge.props as YorumProps;
                return {
                    id: p.kimlik,
                    tur: "yorum",
                    baslik: p.baslik,
                    metin: p.metin,
                    yazar: p.yazar,
                    unvan: p.unvan,
                    dugme: p.dugme,
                    ...(p.ilerlemeGoster === false
                        ? { ilerlemeGoster: false }
                        : {}),
                };
            }
            default: {
                const p = oge.props as PaywallProps;
                return {
                    id: p.kimlik,
                    tur: "paywall",
                    baslik: p.baslik,
                    metin: p.metin,
                    dugme: p.dugme,
                    plan: p.plan,
                    ...(p.ilerlemeGoster === false
                        ? { ilerlemeGoster: false }
                        : {}),
                    ...yasal(p),
                };
            }
        }
    });
    const tanim: Tanim = { sayfaBasligi: k?.sayfaBasligi ?? "", adimlar };
    if (k?.logo) tanim.logo = k.logo;
    if (k?.ozelTema === "evet") {
        tanim.tema = {
            anaRenk: k.anaRenk,
            arkaPlan: k.arkaPlan,
            metinRengi: k.metinRengi,
            yaziTipi: k.yaziTipi,
            kose: Number(k.kose),
        };
    }
    return tanim;
}
