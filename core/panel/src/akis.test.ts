import { describe, expect, it } from "vitest";
import {
    disaridakiBloklar,
    hedefleriYenidenAdlandir,
    ulasilamayanlar,
} from "./akis";
import { tanimdanPuck, type HuniVerisi, type Tanim } from "./donustur";

const VARSAYILAN = {
    anaRenk: "#2563eb",
    arkaPlan: "#ffffff",
    metinRengi: "#111827",
    yaziTipi: "sistem",
    kose: 12,
} as const;

const paywall = {
    id: "paywall",
    tur: "paywall",
    baslik: "P",
    metin: "",
    dugme: "Al",
    plan: "monthly",
} as const;

// Ç55 (1): oynatıcının kuralı (Huni.tsx ileri): hedef varsa ona, yoksa
// sıradakine; çoklu/metin soru ve yorum hep sıradakine. Beklenen listeler
// elle çizilmiş akıştan (tuzak §3).
describe("ulaşılamayan adım", () => {
    it("tanıtım hedefi aradaki adımı atlarsa o adıma yol yok", () => {
        const tanim: Tanim = {
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "giris",
                    tur: "ekran",
                    baslik: "A",
                    bloklar: [],
                    dugme: "B",
                    hedef: "son",
                },
                {
                    id: "ara",
                    tur: "ekran",
                    baslik: "C",
                    bloklar: [],
                    dugme: "D",
                },
                {
                    id: "son",
                    tur: "ekran",
                    baslik: "E",
                    bloklar: [],
                    dugme: "F",
                },
                paywall,
            ],
        };
        expect(ulasilamayanlar(tanim)).toEqual(["ara"]);
    });

    it("T1: ekran hedefi de atlar (tanıtım gibi)", () => {
        const tanim: Tanim = {
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "giris",
                    tur: "ekran",
                    baslik: "A",
                    bloklar: [{ tur: "metin", metin: "m" }],
                    dugme: "B",
                    hedef: "son",
                },
                {
                    id: "ara",
                    tur: "ekran",
                    baslik: "C",
                    bloklar: [],
                    dugme: "D",
                },
                {
                    id: "son",
                    tur: "ekran",
                    baslik: "E",
                    bloklar: [],
                    dugme: "F",
                },
                paywall,
            ],
        };
        expect(ulasilamayanlar(tanim)).toEqual(["ara"]);
    });

    it("T1b: ekrandaki tek seçimli soru seçenek hedeflerine dallanır", () => {
        const ekran = (hedefler: (string | undefined)[]): Tanim => ({
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "giris",
                    tur: "ekran",
                    baslik: "?",
                    bloklar: [
                        {
                            tur: "soru",
                            secenekler: hedefler.map((hedef, j) => ({
                                id: `s${j}`,
                                metin: "S",
                                ...(hedef ? { hedef } : {}),
                            })),
                        },
                    ],
                },
                {
                    id: "ara",
                    tur: "ekran",
                    baslik: "C",
                    bloklar: [],
                    dugme: "D",
                },
                {
                    id: "son",
                    tur: "ekran",
                    baslik: "E",
                    bloklar: [],
                    dugme: "F",
                },
                paywall,
            ],
        });
        expect(ulasilamayanlar(ekran(["son", "son"]))).toEqual(["ara"]);
        expect(ulasilamayanlar(ekran(["son", undefined]))).toEqual([]);
    });

    it("bir seçenek sıradakine giderse dal adımı ulaşılabilir; hepsi atlarsa değil", () => {
        const soru = (hedefler: [string, string]): Tanim => ({
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "s",
                    tur: "ekran",
                    baslik: "?",
                    bloklar: [
                        {
                            tur: "soru",
                            secenekler: [
                                {
                                    id: "a",
                                    metin: "A",
                                    ...(hedefler[0]
                                        ? { hedef: hedefler[0] }
                                        : {}),
                                },
                                {
                                    id: "b",
                                    metin: "B",
                                    ...(hedefler[1]
                                        ? { hedef: hedefler[1] }
                                        : {}),
                                },
                            ],
                        },
                    ],
                },
                {
                    id: "dal",
                    tur: "ekran",
                    baslik: "D",
                    bloklar: [],
                    dugme: "Ok",
                },
                paywall,
            ],
        });
        expect(ulasilamayanlar(soru(["", "paywall"]))).toEqual([]);
        expect(ulasilamayanlar(soru(["paywall", "paywall"]))).toEqual(["dal"]);
    });

    it("çoklu seçim, serbest yanıt ve yorum her zaman sıradakine geçer", () => {
        const tanim: Tanim = {
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "c",
                    tur: "ekran",
                    baslik: "?",
                    bloklar: [
                        {
                            tur: "soru",
                            secimTuru: "coklu",
                            secenekler: [{ id: "a", metin: "A" }],
                        },
                    ],
                },
                {
                    id: "m",
                    tur: "ekran",
                    baslik: "?",
                    bloklar: [{ tur: "soru", secimTuru: "metin" }],
                    dugme: "Devam",
                },
                {
                    id: "y",
                    tur: "ekran",
                    baslik: "Y",
                    bloklar: [
                        { tur: "yorum", metin: "", yazar: "", unvan: "" },
                    ],
                    dugme: "Ok",
                },
                paywall,
            ],
        };
        expect(ulasilamayanlar(tanim)).toEqual([]);
    });
});

// Ç55 (2): adım kimliği değişince ona işaret eden hedefler de değişir.
describe("T1: ekran dışında kalan blok", () => {
    it("kökteki bloklar adlarıyla; ekranın içindekiler sayılmaz", () => {
        const veri = tanimdanPuck(
            {
                sayfaBasligi: "x",
                adimlar: [
                    {
                        id: "e",
                        tur: "ekran",
                        baslik: "A",
                        bloklar: [{ tur: "metin", metin: "içeride" }],
                        dugme: "B",
                    },
                    paywall,
                ],
            },
            VARSAYILAN,
        );
        expect(disaridakiBloklar(veri)).toEqual([]);
        veri.content.splice(1, 0, {
            type: "KutuBlogu",
            props: { id: "KutuBlogu-1", metin: "dışarıda", simge: "" },
        });
        expect(disaridakiBloklar(veri)).toEqual(["KutuBlogu"]);
    });
});

describe("hedefleri yeniden adlandırma", () => {
    const tanim: Tanim = {
        sayfaBasligi: "x",
        adimlar: [
            {
                id: "giris",
                tur: "ekran",
                baslik: "A",
                bloklar: [],
                dugme: "B",
                hedef: "son",
            },
            {
                id: "s",
                tur: "ekran",
                baslik: "?",
                bloklar: [
                    {
                        tur: "soru",
                        secenekler: [
                            { id: "a", metin: "A", hedef: "son" },
                            { id: "b", metin: "B" },
                        ],
                    },
                ],
            },
            { id: "son", tur: "ekran", baslik: "E", bloklar: [], dugme: "F" },
            paywall,
        ],
    };
    const once = tanimdanPuck(tanim, VARSAYILAN);
    const yeniden = (
        veri: HuniVerisi,
        sira: number,
        kimlik: string,
    ): HuniVerisi => ({
        ...veri,
        content: veri.content.map((o, i) =>
            i === sira
                ? ({ ...o, props: { ...o.props, kimlik } } as typeof o)
                : o,
        ),
    });

    it("ekran düğmesi ve soru bloğu seçenek hedefleri yeni kimliğe geçer", () => {
        const sonra = yeniden(once, 2, "kapanis");
        const duzelmis = hedefleriYenidenAdlandir(once, sonra);
        expect(duzelmis).not.toBeNull();
        const props = duzelmis!.content.map(
            (o) => o.props as Record<string, unknown>,
        );
        expect(props[0]?.["hedef"]).toBe("kapanis");
        const blok = (
            props[1]?.["bloklar"] as {
                props: { secenekler: { hedef: string }[] };
            }[]
        )[0]!;
        expect(blok.props.secenekler.map((s) => s.hedef)).toEqual([
            "kapanis",
            "",
        ]);
    });

    it("T1b: ekrandaki soru bloğunun seçenek hedefi de yeni kimliğe geçer", () => {
        const ekranli = tanimdanPuck(
            {
                ...tanim,
                adimlar: [
                    {
                        id: "e",
                        tur: "ekran",
                        baslik: "?",
                        bloklar: [
                            {
                                tur: "soru",
                                secenekler: [
                                    { id: "a", metin: "A", hedef: "son" },
                                    { id: "b", metin: "B" },
                                ],
                            },
                        ],
                    },
                    ...tanim.adimlar.slice(2),
                ],
            },
            VARSAYILAN,
        );
        const duzelmis = hedefleriYenidenAdlandir(
            ekranli,
            yeniden(ekranli, 1, "kapanis"),
        );
        const blok = (
            duzelmis!.content[0]!.props as {
                bloklar: { props: { secenekler: { hedef: string }[] } }[];
            }
        ).bloklar[0]!;
        expect(blok.props.secenekler.map((s) => s.hedef)).toEqual([
            "kapanis",
            "",
        ]);
    });

    it("kimlik değişmediyse ya da eski kimliği başka blok taşıyorsa dokunmaz", () => {
        expect(hedefleriYenidenAdlandir(once, once)).toBeNull();
        // Eski ad başka bir blokta yaşıyorsa hedef ona işaret etmeye devam eder.
        const ikiAd = yeniden(yeniden(once, 2, "kapanis"), 0, "son");
        expect(hedefleriYenidenAdlandir(once, ikiAd)).toBeNull();
    });
});
