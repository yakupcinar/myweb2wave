// T3a: koşul motoru — beklenenler elle (§3), yokluk anlamı açıkça ölçülür.
import { describe, expect, it } from "vitest";
import { grupTutar, kosulTutar, yonlendir } from "./kosul";

const o = {
    "utm.source": "meta",
    "huni.surum": 2,
    "cevap.kiminle": ["aile", "is"],
    "cevap.not": "kısa",
};

describe("T3a — tek koşul", () => {
    it.each([
        ["utm.source", "esit", "meta", true],
        ["utm.source", "esit", "Meta", false],
        ["utm.source", "esit_degil", "tiktok", true],
        ["utm.campaign", "esit_degil", "yaz", true], // yok ≠ yaz
        ["utm.campaign", "esit", "yaz", false],
        ["huni.surum", "esit", 2, true],
        ["huni.surum", "esit", "2", false], // tür de eşit olmalı
        ["cevap.kiminle", "icerir", "aile", true],
        ["cevap.kiminle", "icerir", "arkadas", false],
        ["cevap.kiminle", "icermez", "arkadas", true],
        ["cevap.yok", "icermez", "a", true], // cevaplanmamış soru içermez
        ["cevap.yok", "icerir", "a", false],
        ["cevap.not", "icerir", "kısa", false], // metin liste değil
        ["izin.reklam", "var", undefined, false],
        ["izin.reklam", "yok", undefined, true],
        ["cevap.not", "var", undefined, true],
    ] as const)("%s %s %s → %s", (ozellik, islem, deger, beklenen) => {
        expect(
            kosulTutar(
                {
                    ozellik,
                    islem,
                    ...(deger === undefined ? {} : { deger }),
                },
                o,
            ),
        ).toBe(beklenen);
    });
});

describe("T3a — kurallar", () => {
    const meta = {
        ozellik: "utm.source",
        islem: "esit",
        deger: "meta",
    } as const;
    const tiktok = {
        ozellik: "utm.source",
        islem: "esit",
        deger: "tiktok",
    } as const;
    const aile = {
        ozellik: "cevap.kiminle",
        islem: "icerir",
        deger: "aile",
    } as const;
    it("hepsi (varsayılan) = VE; herhangi = VEYA", () => {
        expect(yonlendir([{ kosullar: [meta, tiktok], hedef: "x" }], o)).toBe(
            undefined,
        );
        expect(
            yonlendir(
                [{ baglac: "herhangi", kosullar: [tiktok, aile], hedef: "x" }],
                o,
            ),
        ).toBe("x");
        expect(yonlendir([{ kosullar: [meta, aile], hedef: "x" }], o)).toBe(
            "x",
        );
    });
    it("ilk tutan kural kazanır; hiçbiri tutmazsa tanımsız; kural yoksa tanımsız", () => {
        expect(
            yonlendir(
                [
                    { kosullar: [tiktok], hedef: "a" },
                    { kosullar: [meta], hedef: "b" },
                    { kosullar: [aile], hedef: "c" },
                ],
                o,
            ),
        ).toBe("b");
        expect(yonlendir([{ kosullar: [tiktok], hedef: "a" }], o)).toBe(
            undefined,
        );
        expect(yonlendir(undefined, o)).toBe(undefined);
    });
});

// T3b: gösterim koşulu aynı grup kuralı (VE varsayılan, herhangi = VEYA).
describe("T3b — koşul grubu", () => {
    const aile = {
        ozellik: "cevap.kiminle",
        islem: "icerir",
        deger: "aile",
    } as const;
    const tiktok = {
        ozellik: "utm.source",
        islem: "esit",
        deger: "tiktok",
    } as const;
    it("hepsi: biri tutmazsa tutmaz", () => {
        expect(grupTutar({ kosullar: [aile] }, o)).toBe(true);
        expect(grupTutar({ kosullar: [aile, tiktok] }, o)).toBe(false);
    });
    it("herhangi: biri yeter", () => {
        expect(
            grupTutar({ baglac: "herhangi", kosullar: [aile, tiktok] }, o),
        ).toBe(true);
        expect(grupTutar({ baglac: "herhangi", kosullar: [tiktok] }, o)).toBe(
            false,
        );
    });
});
