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

export type SecenekProps = { kimlik: string; metin: string; gorsel: string };
export type TanitimProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    dugme: string;
    gorsel: string;
};
export type SoruProps = {
    kimlik: string;
    soru: string;
    secenekler: SecenekProps[];
};
export type PaywallProps = {
    kimlik: string;
    baslik: string;
    metin: string;
    dugme: string;
    plan: Plan;
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
    Paywall: PaywallProps;
};
export type HuniVerisi = Data<Bilesenler, KokProps>;

/** Boş dize = görsel yok (Puck alanı boş değer taşır; tanımda alan hiç olmaz). */
const gorselli = <T extends object>(
    nesne: T,
    gorsel: string,
): T & { gorsel?: string } => (gorsel ? { ...nesne, gorsel } : nesne);

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
                        },
                    };
                case "soru":
                    return {
                        type: "Soru" as const,
                        props: {
                            id,
                            kimlik: adim.id,
                            soru: adim.soru,
                            secenekler: adim.secenekler.map((s) => ({
                                kimlik: s.id,
                                metin: s.metin,
                                gorsel: s.gorsel ?? "",
                            })),
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
                return gorselli(
                    {
                        id: p.kimlik,
                        tur: "tanitim" as const,
                        baslik: p.baslik,
                        metin: p.metin,
                        dugme: p.dugme,
                    },
                    p.gorsel,
                );
            }
            case "Soru": {
                const p = oge.props as SoruProps;
                return {
                    id: p.kimlik,
                    tur: "soru",
                    soru: p.soru,
                    secenekler: (p.secenekler ?? []).map((s) =>
                        gorselli(
                            { id: s.kimlik, metin: s.metin },
                            s.gorsel ?? "",
                        ),
                    ),
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
