// Huni tanımı (çekirdeğin biçimi, contract HuniTanimi) ↔ Puck verisi.
// Çekirdek yalnız tanımı bilir; Puck yalnız düzenleyicide. Çeviri KAYIPSIZ
// olmalı: tanım → Puck → tanım aynısını vermeli (donustur.test.ts ölçer).
// Puck her bileşene kendi `id`'sini verir; bizim adım kimliğimiz `kimlik`.
import type { Data, Slot } from "@puckeditor/core";
import type { components } from "./sozlesme";

export type Tanim = components["schemas"]["HuniTanimi"];
export type Tema = components["schemas"]["HuniTemasi"];
type Zemin = NonNullable<Tema["zemin"]>;
type OnAyar<K extends "baslikBoyu" | "yogunluk" | "hizalama" | "dugmeBicimi"> =
    NonNullable<Tema[K]>;
type Adim = Tanim["adimlar"][number];
type Plan = components["schemas"]["PaywallAdimi"]["plan"];
type EkranAdimi = components["schemas"]["EkranAdimi"];
type EkranBlogu = components["schemas"]["EkranBlogu"];
type YaziTipi = Tema["yaziTipi"];

export type SecenekProps = {
    kimlik: string;
    metin: string;
    /** Mobil görünüm 3: boş = simge yok (tanıma yazılmaz). */
    simge?: string;
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
    /** G5: "ust" tanıma yazılmaz. */
    duzen: "ust" | "kahraman" | "tamEkran";
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
    /** G4: "liste" tanıma yazılmaz; serbest yanıtta yok sayılır. */
    duzen: "liste" | "izgara";
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
/** Mobil görünüm 4: Puck dizi alanı nesne ister — madde {metin}. Süre 4 tanıma yazılmaz. */
export type YuklemeProps = {
    kimlik: string;
    baslik: string;
    maddeler: { metin: string }[];
    sure: number;
    ilerlemeGoster: boolean;
};
/**
 * T1: ekran adımı; bloklar Puck yuvasında (slot) ayrı bileşenler. Puck
 * veride yuvayı {type, props} listesi olarak taşır.
 */
export type EkranProps = {
    kimlik: string;
    baslik: string;
    bloklar: Slot;
    dugme: string;
    hedef: string;
    ilerlemeGoster: boolean;
};
/** Blok bileşenlerinin alanları; boş isteğe bağlı alan tanıma yazılmaz. */
export type BaslikBloguProps = { metin: string };
export type MetinBloguProps = { metin: string };
export type GorselBloguProps = { gorsel: string; gorselMetni: string };
export type MaddelerBloguProps = { maddeler: { metin: string }[] };
/** 0 = puan yok. */
export type YorumBloguProps = {
    metin: string;
    yazar: string;
    unvan: string;
    puan: number;
};
export type IstatistikBloguProps = { deger: string; metin: string };
export type KutuBloguProps = { metin: string; simge: string };
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
    /** G3: "yok" = düz arka plan; alanlar yalnız seçilen türde kullanılır. */
    zeminTuru: "yok" | "gecis" | "gorsel";
    ikinciRenk: string;
    zeminGorseli: string;
    karartma: number;
    /** G6: "yok" = içerik doğrudan zeminde. */
    kart: "yok" | "var";
    kartRengi: string;
    /** G7: "normal"/"sol" tanıma yazılmaz (yoksa aynı anlam). */
    baslikBoyu: OnAyar<"baslikBoyu">;
    yogunluk: OnAyar<"yogunluk">;
    hizalama: OnAyar<"hizalama">;
    /** G4: "dolu" tanıma yazılmaz. */
    dugmeBicimi: OnAyar<"dugmeBicimi">;
};
export type Bilesenler = {
    Tanitim: TanitimProps;
    TekSecim: SoruProps;
    CokluSecim: SoruProps;
    SerbestYanit: SoruProps;
    Yorum: YorumProps;
    Yukleme: YuklemeProps;
    Ekran: EkranProps;
    Paywall: PaywallProps;
    BaslikBlogu: BaslikBloguProps;
    MetinBlogu: MetinBloguProps;
    GorselBlogu: GorselBloguProps;
    MaddelerBlogu: MaddelerBloguProps;
    YorumBlogu: YorumBloguProps;
    IstatistikBlogu: IstatistikBloguProps;
    KutuBlogu: KutuBloguProps;
};
export type HuniVerisi = Data<Bilesenler, KokProps>;

/** Yanıt biçimi → Puck bloğu (Ç61): kayıt tek `soru` şeması, düzenleyicide üç tür. */
const SORU_TURU = {
    tekli: "TekSecim",
    coklu: "CokluSecim",
    metin: "SerbestYanit",
} as const;

/** T1: blok türü (tanım) ↔ Puck bileşeni. */
export const BLOK_BILESENI = {
    baslik: "BaslikBlogu",
    metin: "MetinBlogu",
    gorsel: "GorselBlogu",
    maddeler: "MaddelerBlogu",
    yorum: "YorumBlogu",
    istatistik: "IstatistikBlogu",
    kutu: "KutuBlogu",
} as const satisfies Record<EkranBlogu["tur"], keyof Bilesenler>;
const BLOK_TURU = Object.fromEntries(
    Object.entries(BLOK_BILESENI).map(([t, b]) => [b, t]),
) as Record<string, EkranBlogu["tur"]>;
/** Puck'ta yalnız ekranın yuvasına konan bileşenler. */
export const BLOK_BILESENLERI: string[] = Object.values(BLOK_BILESENI);

function bloktanPuck(b: EkranBlogu, id: string) {
    switch (b.tur) {
        case "baslik":
        case "metin":
            return {
                type: BLOK_BILESENI[b.tur],
                props: { id, metin: b.metin },
            };
        case "gorsel":
            return {
                type: "GorselBlogu" as const,
                props: {
                    id,
                    gorsel: b.gorsel,
                    gorselMetni: b.gorselMetni ?? "",
                },
            };
        case "maddeler":
            return {
                type: "MaddelerBlogu" as const,
                props: { id, maddeler: b.maddeler.map((metin) => ({ metin })) },
            };
        case "yorum":
            return {
                type: "YorumBlogu" as const,
                props: {
                    id,
                    metin: b.metin,
                    yazar: b.yazar,
                    unvan: b.unvan ?? "",
                    puan: b.puan ?? 0,
                },
            };
        case "istatistik":
            return {
                type: "IstatistikBlogu" as const,
                props: { id, deger: b.deger, metin: b.metin },
            };
        case "kutu":
            return {
                type: "KutuBlogu" as const,
                props: { id, metin: b.metin, simge: b.simge ?? "" },
            };
    }
}

/**
 * Puck bloğu → tanım bloğu. Katalog dışı bileşen (ekranın içine başka adım
 * bırakılırsa) türüyle olduğu gibi gider — çekirdek reddeder, kaybolmaz.
 */
export function puckBloktan(oge: {
    type: string;
    props: Record<string, unknown>;
}): EkranBlogu {
    const p = oge.props;
    const metin = String(p["metin"] ?? "");
    switch (BLOK_TURU[oge.type]) {
        case "baslik":
            return { tur: "baslik", metin };
        case "metin":
            return { tur: "metin", metin };
        case "gorsel":
            return gorselli(
                { tur: "gorsel" as const },
                String(p["gorsel"] ?? ""),
                String(p["gorselMetni"] ?? ""),
            ) as EkranBlogu;
        case "maddeler":
            return {
                tur: "maddeler",
                maddeler: ((p["maddeler"] ?? []) as { metin: string }[])
                    .map((m) => m.metin)
                    .filter(Boolean),
            };
        case "yorum": {
            const unvan = String(p["unvan"] ?? "");
            const puan = Number(p["puan"] ?? 0);
            return {
                tur: "yorum",
                metin,
                yazar: String(p["yazar"] ?? ""),
                ...(unvan ? { unvan } : {}),
                ...(puan ? { puan } : {}),
            };
        }
        case "istatistik":
            return {
                tur: "istatistik",
                deger: String(p["deger"] ?? ""),
                metin,
            };
        case "kutu": {
            const simge = String(p["simge"] ?? "");
            return { tur: "kutu", metin, ...(simge ? { simge } : {}) };
        }
        default:
            return { tur: oge.type } as unknown as EkranBlogu;
    }
}

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
                zeminTuru: tema.zemin?.tur ?? "yok",
                ikinciRenk:
                    tema.zemin?.tur === "gecis"
                        ? tema.zemin.ikinciRenk
                        : tema.arkaPlan,
                zeminGorseli:
                    tema.zemin?.tur === "gorsel" ? tema.zemin.gorsel : "",
                karartma:
                    tema.zemin?.tur === "gorsel" ? tema.zemin.karartma : 70,
                kart: tema.kartRengi ? "var" : "yok",
                kartRengi: tema.kartRengi ?? "#ffffff",
                baslikBoyu: tema.baslikBoyu ?? "normal",
                yogunluk: tema.yogunluk ?? "normal",
                hizalama: tema.hizalama ?? "sol",
                dugmeBicimi: tema.dugmeBicimi ?? "dolu",
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
                            duzen: adim.duzen ?? "ust",
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
                            duzen: adim.duzen ?? "liste",
                            secenekler: (adim.secenekler ?? []).map((s) => ({
                                kimlik: s.id,
                                metin: s.metin,
                                simge: s.simge ?? "",
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
                case "yukleme":
                    return {
                        type: "Yukleme" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            baslik: adim.baslik,
                            maddeler: (adim.maddeler ?? []).map((metin) => ({
                                metin,
                            })),
                            sure: adim.sure ?? 4,
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                        },
                    };
                case "ekran":
                    return {
                        type: "Ekran" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            baslik: adim.baslik,
                            bloklar: adim.bloklar.map((b, j) =>
                                bloktanPuck(b, `blok-${i}-${j}`),
                            ),
                            dugme: adim.dugme,
                            hedef: adim.hedef ?? "",
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
                            ...(p.duzen && p.duzen !== "ust"
                                ? { duzen: p.duzen }
                                : {}),
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
                    ...(p.secimTuru !== "metin" && p.duzen === "izgara"
                        ? { duzen: "izgara" as const }
                        : {}),
                    ...(p.secimTuru === "metin"
                        ? {}
                        : {
                              secenekler: (p.secenekler ?? []).map((s) => {
                                  const secenek = gorselli(
                                      {
                                          id: s.kimlik,
                                          metin: s.metin,
                                          ...(s.simge
                                              ? { simge: s.simge }
                                              : {}),
                                      },
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
            case "Yukleme": {
                const p = oge.props as YuklemeProps;
                const maddeler = (p.maddeler ?? []).map((m) => m.metin);
                return {
                    id: p.kimlik,
                    tur: "yukleme",
                    baslik: p.baslik,
                    ...(maddeler.length ? { maddeler } : {}),
                    ...(Number(p.sure) !== 4 ? { sure: Number(p.sure) } : {}),
                    ...(p.ilerlemeGoster === false
                        ? { ilerlemeGoster: false }
                        : {}),
                };
            }
            case "Ekran": {
                const p = oge.props as EkranProps;
                return hedefli(
                    {
                        id: p.kimlik,
                        tur: "ekran" as const,
                        baslik: p.baslik,
                        bloklar: (p.bloklar ?? []).map(puckBloktan),
                        dugme: p.dugme,
                        ...(p.ilerlemeGoster === false
                            ? { ilerlemeGoster: false }
                            : {}),
                    } satisfies EkranAdimi,
                    p.hedef ?? "",
                );
            }
            case "Paywall": {
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
            default:
                // T1: ekran dışına bırakılmış blok — düzenleyici uyarır
                // (akis.ts), çekirdek türüyle reddeder; sessizce düşmez.
                return {
                    id: String(oge.props["id"]),
                    tur: oge.type,
                } as unknown as Adim;
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
            ...(k.kart === "var" ? { kartRengi: k.kartRengi } : {}),
            ...kokOnAyarlari(k),
            ...kokZemini(k),
        };
    }
    return tanim;
}

/** G3: kök alanlarından tanımın zemini; "yok" → alan yazılmaz (uydurulmaz). */
export function kokZemini(
    k: Pick<KokProps, "zeminTuru" | "ikinciRenk" | "zeminGorseli" | "karartma">,
): { zemin?: Zemin } {
    if (k.zeminTuru === "gecis")
        return { zemin: { tur: "gecis", ikinciRenk: k.ikinciRenk } };
    if (k.zeminTuru === "gorsel")
        return {
            zemin: {
                tur: "gorsel",
                gorsel: k.zeminGorseli,
                karartma: Number(k.karartma),
            },
        };
    return {};
}

/** G7: kök alanlarından yerleşim ön ayarları; varsayılan değer yazılmaz. */
export function kokOnAyarlari(
    k: Pick<KokProps, "baslikBoyu" | "yogunluk" | "hizalama" | "dugmeBicimi">,
): Pick<Tema, "baslikBoyu" | "yogunluk" | "hizalama" | "dugmeBicimi"> {
    return {
        ...(k.baslikBoyu !== "normal" ? { baslikBoyu: k.baslikBoyu } : {}),
        ...(k.yogunluk !== "normal" ? { yogunluk: k.yogunluk } : {}),
        ...(k.hizalama !== "sol" ? { hizalama: k.hizalama } : {}),
        ...(k.dugmeBicimi && k.dugmeBicimi !== "dolu"
            ? { dugmeBicimi: k.dugmeBicimi }
            : {}),
    };
}
