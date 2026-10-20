import { describe, expect, it } from "vitest";
import { puckTanim, tanimdanPuck, type Tanim, type Tema } from "./donustur";

const VARSAYILAN: Tema = {
    anaRenk: "#2563eb",
    arkaPlan: "#ffffff",
    metinRengi: "#111827",
    yaziTipi: "sistem",
    kose: 10,
};
const G1 = "a".repeat(64);
const G2 = "b".repeat(64);

const temel: Tanim = {
    sayfaBasligi: "KeepChat Pro",
    adimlar: [
        {
            id: "karsilama",
            tur: "tanitim",
            baslik: "Merhaba",
            metin: "Kısa",
            dugme: "Başla",
        },
        {
            id: "kiminle",
            tur: "soru",
            soru: "Kiminle?",
            secenekler: [
                { id: "aile", metin: "Ailemle" },
                { id: "is", metin: "İş" },
            ],
        },
        {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "9,99 €",
            dugme: "Abone ol",
            plan: "monthly",
        },
    ],
};

describe("tanım ↔ Puck (kayıpsız)", () => {
    it("temasız ve görselsiz tanım aynen döner — tema/görsel alanı UYDURULMAZ", () => {
        expect(puckTanim(tanimdanPuck(temel, VARSAYILAN))).toStrictEqual(temel);
    });

    it("özel tema ve görseller aynen döner", () => {
        const zengin: Tanim = {
            ...temel,
            tema: {
                anaRenk: "#16a34a",
                arkaPlan: "#0b1220",
                metinRengi: "#f8fafc",
                yaziTipi: "yuvarlak",
                kose: 20,
            },
            adimlar: [
                {
                    id: "karsilama",
                    tur: "tanitim",
                    baslik: "Merhaba",
                    metin: "",
                    dugme: "Başla",
                    gorsel: G1,
                },
                {
                    id: "kiminle",
                    tur: "soru",
                    soru: "Kiminle?",
                    secenekler: [
                        { id: "aile", metin: "Ailemle", gorsel: G2 },
                        { id: "is", metin: "İş" },
                    ],
                },
                {
                    id: "paywall",
                    tur: "paywall",
                    baslik: "Pro",
                    metin: "",
                    dugme: "Abone ol",
                    plan: "monthly",
                },
            ],
        };
        expect(puckTanim(tanimdanPuck(zengin, VARSAYILAN))).toStrictEqual(
            zengin,
        );
    });

    it("Puck'ta sıra değişirse tanımda da değişir (sürükle-bırak)", () => {
        const veri = tanimdanPuck(temel, VARSAYILAN);
        veri.content = [veri.content[1]!, veri.content[0]!, veri.content[2]!];
        expect(puckTanim(veri).adimlar.map((a) => a.id)).toStrictEqual([
            "kiminle",
            "karsilama",
            "paywall",
        ]);
    });

    it("Puck bileşen kimlikleri benzersiz, adım kimliği ayrı alanda", () => {
        const veri = tanimdanPuck(temel, VARSAYILAN);
        const idler = veri.content.map((o) => o.props.id);
        expect(new Set(idler).size).toBe(idler.length);
        expect(veri.content.map((o) => o.props.kimlik)).toStrictEqual([
            "karsilama",
            "kiminle",
            "paywall",
        ]);
    });

    it("tema kapatılınca tanımdan çıkar; köşe sayıya çevrilir", () => {
        const veri = tanimdanPuck({ ...temel, tema: VARSAYILAN }, VARSAYILAN);
        expect(puckTanim(veri).tema).toStrictEqual(VARSAYILAN);
        veri.root.props!.kose = "14" as unknown as number; // Puck sayı alanı dize verebilir
        expect(puckTanim(veri).tema?.kose).toBe(14);
        veri.root.props!.ozelTema = "hayir";
        expect("tema" in puckTanim(veri)).toBe(false);
    });
});
