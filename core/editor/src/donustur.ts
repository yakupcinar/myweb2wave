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
    hedef: string;
};
export type TanitimProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    dugme: string;
    gorsel: string;
    hedef: string;
    ilerlemeGoster: boolean;
};
export type SoruProps = {
    kimlik: string;
    soru: string;
    secenekler: SecenekProps[];
    secimTuru: "tekli" | "coklu";
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
    ozelTema: "evet" | "hayir";
    anaRenk: string;
    arkaPlan: string;
    metinRengi: string;
    yaziTipi: YaziTipi;
    kose: number;
};
export type Bilesenler = {
    Tanitim: TanitimProps;
    Soru: SoruProps;
    Yorum: YorumProps;
    Paywall: PaywallProps;
};
export type HuniVerisi = Data<Bilesenler, KokProps>;

/** Boş dize = görsel yok (Puck alanı boş değer taşır; tanımda alan hiç olmaz). */
const gorselli = <T extends object>(
    nesne: T,
    gorsel: string,
): T & { gorsel?: string } => (gorsel ? { ...nesne, gorsel } : nesne);
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
                            hedef: adim.hedef ?? "",
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                        },
                    };
                case "soru":
                    return {
                        type: "Soru" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            soru: adim.soru,
                            ilerlemeGoster: adim.ilerlemeGoster ?? true,
                            secimTuru: adim.secimTuru ?? "tekli",
                            devamDugmesi: adim.devamDugmesi ?? "Devam",
                            secenekler: adim.secenekler.map((s) => ({
                                kimlik: s.id,
                                metin: s.metin,
                                gorsel: s.gorsel ?? "",
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
                        },
                    };
            }
        }),
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
                    ),
                    p.hedef ?? "",
                );
            }
            case "Soru": {
                const p = oge.props as SoruProps;
                return {
                    id: p.kimlik,
                    tur: "soru",
                    soru: p.soru,
                    ...(p.ilerlemeGoster === false
                        ? { ilerlemeGoster: false }
                        : {}),
                    ...(p.secimTuru === "coklu"
                        ? { secimTuru: "coklu" as const }
                        : {}),
                    ...(p.devamDugmesi !== "Devam"
                        ? { devamDugmesi: p.devamDugmesi }
                        : {}),
                    secenekler: (p.secenekler ?? []).map((s) => {
                        const secenek = gorselli(
                            { id: s.kimlik, metin: s.metin },
                            s.gorsel ?? "",
                        );
                        return p.secimTuru === "coklu"
                            ? secenek
                            : hedefli(secenek, s.hedef ?? "");
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
                };
            }
        }
    });
    const tanim: Tanim = { sayfaBasligi: k?.sayfaBasligi ?? "", adimlar };
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
