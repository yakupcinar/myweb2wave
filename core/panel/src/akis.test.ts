import { describe, expect, it } from "vitest";
import {
    disaridakiBloklar,
    ekranDugmeleri,
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

// Ç87 + Ç84: tekli soruda düğme yok; çoklu / serbest yanıtta düğme gerekir ve
// önizlemede hunideki gibi ilk açılışta kapalı. Beklenenler elle (§3).
describe("ekran düğmesi yuvadaki soruya uyar (Ç84, Ç87)", () => {
    const veri = (
        bloklar: { type: string; props: Record<string, unknown> }[],
        dugme: string,
        onizlemeKapali?: boolean,
    ): HuniVerisi =>
        ({
            root: { props: {} },
            content: [
                {
                    type: "Ekran",
                    props: {
                        id: "Ekran-1",
                        kimlik: "e",
                        bloklar,
                        dugme,
                        ...(onizlemeKapali === undefined
                            ? {}
                            : { onizlemeKapali }),
                    },
                },
            ],
        }) as unknown as HuniVerisi;
    const soru = (ek: Record<string, unknown>) => ({
        type: "SoruBlogu",
        props: { id: "SoruBlogu-1", secimTuru: "tekli", ...ek },
    });
    const props = (v: HuniVerisi | null) =>
        v?.content[0]?.props as unknown as Record<string, unknown>;

    it("tek seçimli soru eklenince düğme boşalır, önizleme açık", () => {
        const p = props(ekranDugmeleri(veri([soru({})], "Devam")));
        expect(p["dugme"]).toBe("");
        expect(p["onizlemeKapali"]).toBe(false);
    });
    it("çoklu seçimde düğme yoksa Devam gelir, önizlemede kapalı; yazılmış düğme korunur", () => {
        const p = props(
            ekranDugmeleri(veri([soru({ secimTuru: "coklu" })], "")),
        );
        expect(p["dugme"]).toBe("Devam");
        expect(p["onizlemeKapali"]).toBe(true);
        expect(
            props(
                ekranDugmeleri(veri([soru({ secimTuru: "coklu" })], "Gönder")),
            )["dugme"],
        ).toBe("Gönder");
    });
    it("serbest yanıt: zorunluysa önizlemede kapalı, isteğe bağlıysa açık", () => {
        const zorunlu = soru({ secimTuru: "metin", yanitZorunlu: "zorunlu" });
        expect(
            props(ekranDugmeleri(veri([zorunlu], "Devam")))["onizlemeKapali"],
        ).toBe(true);
        expect(
            ekranDugmeleri(
                veri(
                    [
                        soru({
                            secimTuru: "metin",
                            yanitZorunlu: "istege_bagli",
                        }),
                    ],
                    "Devam",
                    false,
                ),
            ),
        ).toBeNull();
    });
    it("değişiklik yoksa null (bekçi döngüye girmez); sorusuz ekrana dokunulmaz", () => {
        expect(
            ekranDugmeleri(
                veri([{ type: "MetinBlogu", props: { id: "m" } }], ""),
            ),
        ).toBeNull();
        const once = ekranDugmeleri(veri([soru({ secimTuru: "coklu" })], ""));
        expect(ekranDugmeleri(once!)).toBeNull();
    });
});

// T3a: kural hedefleri de yol; kimlik değişince kural hedefi ve koşuldaki
// cevap.<eski> taşınır (taşınmasa kural sessizce hiç tutmazdı).
describe("T3a: yönlendirme kuralları", () => {
    const kurallı = (hedef: string): Tanim => ({
        sayfaBasligi: "x",
        adimlar: [
            {
                id: "s",
                tur: "ekran",
                baslik: "?",
                bloklar: [],
                dugme: "D",
                hedef: "son",
                yonlendirme: [
                    {
                        kosullar: [
                            {
                                ozellik: "utm.source",
                                islem: "esit",
                                deger: "meta",
                            },
                        ],
                        hedef,
                    },
                ],
            },
            { id: "ara", tur: "ekran", baslik: "C", bloklar: [], dugme: "D" },
            { id: "son", tur: "ekran", baslik: "E", bloklar: [], dugme: "F" },
            paywall,
        ],
    });
    it("kural hedefi ulaşılabilir sayılır", () => {
        expect(ulasilamayanlar(kurallı("son"))).toEqual(["ara"]);
        expect(ulasilamayanlar(kurallı("ara"))).toEqual([]);
    });
    it("kimlik değişince kural hedefi ve koşuldaki cevap adı taşınır", () => {
        const t: Tanim = {
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
                                { id: "a", metin: "A" },
                                { id: "b", metin: "B" },
                            ],
                        },
                    ],
                    yonlendirme: [
                        {
                            kosullar: [
                                {
                                    ozellik: "cevap.s",
                                    islem: "icerir",
                                    deger: "a",
                                },
                            ],
                            hedef: "son",
                        },
                    ],
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
        const once = tanimdanPuck(t, VARSAYILAN);
        const ad = (v: HuniVerisi, i: number, kimlik: string): HuniVerisi => ({
            ...v,
            content: v.content.map((o, j) =>
                j === i
                    ? ({ ...o, props: { ...o.props, kimlik } } as typeof o)
                    : o,
            ),
        });
        const kural = (v: HuniVerisi | null) =>
            (
                v!.content[0]!.props as {
                    yonlendirme: {
                        hedef: string;
                        kosullar: { ozellik: string }[];
                    }[];
                }
            ).yonlendirme[0]!;
        expect(
            kural(hedefleriYenidenAdlandir(once, ad(once, 1, "kapanis"))).hedef,
        ).toBe("kapanis");
        expect(
            kural(hedefleriYenidenAdlandir(once, ad(once, 0, "kiminle")))
                .kosullar[0]!.ozellik,
        ).toBe("cevap.kiminle");
    });
});
