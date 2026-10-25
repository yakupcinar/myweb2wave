import { describe, expect, it } from "vitest";
import { hedefleriYenidenAdlandir, ulasilamayanlar } from "./akis";
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
                    tur: "tanitim",
                    baslik: "A",
                    metin: "",
                    dugme: "B",
                    hedef: "son",
                },
                {
                    id: "ara",
                    tur: "tanitim",
                    baslik: "C",
                    metin: "",
                    dugme: "D",
                },
                {
                    id: "son",
                    tur: "tanitim",
                    baslik: "E",
                    metin: "",
                    dugme: "F",
                },
                paywall,
            ],
        };
        expect(ulasilamayanlar(tanim)).toEqual(["ara"]);
    });

    it("bir seçenek sıradakine giderse dal adımı ulaşılabilir; hepsi atlarsa değil", () => {
        const soru = (hedefler: [string, string]): Tanim => ({
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "s",
                    tur: "soru",
                    soru: "?",
                    secenekler: [
                        {
                            id: "a",
                            metin: "A",
                            ...(hedefler[0] ? { hedef: hedefler[0] } : {}),
                        },
                        {
                            id: "b",
                            metin: "B",
                            ...(hedefler[1] ? { hedef: hedefler[1] } : {}),
                        },
                    ],
                },
                {
                    id: "dal",
                    tur: "tanitim",
                    baslik: "D",
                    metin: "",
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
                    tur: "soru",
                    soru: "?",
                    secimTuru: "coklu",
                    secenekler: [{ id: "a", metin: "A" }],
                },
                { id: "m", tur: "soru", soru: "?", secimTuru: "metin" },
                {
                    id: "y",
                    tur: "yorum",
                    baslik: "Y",
                    metin: "",
                    yazar: "",
                    unvan: "",
                    dugme: "Ok",
                },
                paywall,
            ],
        };
        expect(ulasilamayanlar(tanim)).toEqual([]);
    });
});

// Ç55 (2): adım kimliği değişince ona işaret eden hedefler de değişir.
describe("hedefleri yeniden adlandırma", () => {
    const tanim: Tanim = {
        sayfaBasligi: "x",
        adimlar: [
            {
                id: "giris",
                tur: "tanitim",
                baslik: "A",
                metin: "",
                dugme: "B",
                hedef: "son",
            },
            {
                id: "s",
                tur: "soru",
                soru: "?",
                secenekler: [
                    { id: "a", metin: "A", hedef: "son" },
                    { id: "b", metin: "B" },
                ],
            },
            { id: "son", tur: "tanitim", baslik: "E", metin: "", dugme: "F" },
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

    it("tanıtım ve seçenek hedefleri yeni kimliğe geçer", () => {
        const sonra = yeniden(once, 2, "kapanis");
        const duzelmis = hedefleriYenidenAdlandir(once, sonra);
        expect(duzelmis).not.toBeNull();
        const props = duzelmis!.content.map(
            (o) => o.props as Record<string, unknown>,
        );
        expect(props[0]?.["hedef"]).toBe("kapanis");
        expect(
            (props[1]?.["secenekler"] as { hedef: string }[]).map(
                (s) => s.hedef,
            ),
        ).toEqual(["kapanis", ""]);
    });

    it("kimlik değişmediyse ya da eski kimliği başka blok taşıyorsa dokunmaz", () => {
        expect(hedefleriYenidenAdlandir(once, once)).toBeNull();
        // Eski ad başka bir blokta yaşıyorsa hedef ona işaret etmeye devam eder.
        const ikiAd = yeniden(yeniden(once, 2, "kapanis"), 0, "son");
        expect(hedefleriYenidenAdlandir(once, ikiAd)).toBeNull();
    });
});
