import { describe, expect, it } from "vitest";
import {
    puckTanim,
    tanimdanPuck,
    type HuniVerisi,
    type Tanim,
    type Tema,
} from "./donustur";

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
            id: "yorum",
            tur: "yorum",
            baslik: "Deneyim",
            metin: "Çok faydalı",
            yazar: "Ayşe",
            unvan: "Kullanıcı",
            dugme: "Devam",
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

    it("ilerleme çubuğu kapatma ayarı tanıma yazılır; varsayılan ayar eklenmez", () => {
        const kapali: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((adim, sira) =>
                sira === 1 ? { ...adim, ilerlemeGoster: false } : adim,
            ),
        };
        expect(puckTanim(tanimdanPuck(kapali, VARSAYILAN))).toStrictEqual(
            kapali,
        );
        expect(puckTanim(tanimdanPuck(temel, VARSAYILAN))).toStrictEqual(temel);
    });

    it("soru açıklaması korunur; boş editör alanı tanıma eklenmez", () => {
        const aciklamali: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((adim) =>
                adim.tur === "soru"
                    ? {
                          ...adim,
                          aciklama: "Birden fazla seçenek seçebilirsin.",
                      }
                    : adim,
            ),
        };
        expect(puckTanim(tanimdanPuck(aciklamali, VARSAYILAN))).toStrictEqual(
            aciklamali,
        );
        expect(puckTanim(tanimdanPuck(temel, VARSAYILAN))).toStrictEqual(temel);
    });

    it("çoklu seçim metniyle saklanır; seçenek dalları kaldırılır", () => {
        const coklu: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((adim) =>
                adim.tur === "soru"
                    ? {
                          ...adim,
                          secimTuru: "coklu",
                          devamDugmesi: "Seçimleri gönder",
                          secenekler: (adim.secenekler ?? []).map(
                              (secenek) => ({
                                  ...secenek,
                                  hedef: "paywall",
                              }),
                          ),
                      }
                    : adim,
            ),
        };
        const veri = tanimdanPuck(coklu, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual({
            ...coklu,
            adimlar: coklu.adimlar.map((adim) =>
                adim.tur === "soru"
                    ? {
                          ...adim,
                          secenekler: (adim.secenekler ?? []).map(
                              ({ id, metin }) => ({
                                  id,
                                  metin,
                              }),
                          ),
                      }
                    : adim,
            ),
        });
    });

    it("serbest yanıt türü seçenekleri kaldırır ve zorunluluğu kayıpsız taşır", () => {
        const metin: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((adim) =>
                adim.tur === "soru"
                    ? {
                          id: adim.id,
                          tur: "soru",
                          soru: adim.soru,
                          secimTuru: "metin",
                          yanitZorunlu: true,
                          devamDugmesi: "Yanıtla",
                      }
                    : adim,
            ),
        };
        expect(puckTanim(tanimdanPuck(metin, VARSAYILAN))).toStrictEqual(metin);
    });

    it("özel tema, görseller ve hedefler (H5) aynen döner", () => {
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
                    hedef: "kiminle",
                },
                {
                    id: "kiminle",
                    tur: "soru",
                    soru: "Kiminle?",
                    aciklama: "Birden fazla seçenek seçebilirsin.",
                    secenekler: [
                        {
                            id: "aile",
                            metin: "Ailemle",
                            gorsel: G2,
                            hedef: "paywall",
                        },
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
        veri.content = [
            veri.content[1]!,
            veri.content[0]!,
            veri.content[2]!,
            veri.content[3]!,
        ];
        expect(puckTanim(veri).adimlar.map((a) => a.id)).toStrictEqual([
            "kiminle",
            "karsilama",
            "yorum",
            "paywall",
        ]);
    });

    it("hazır soru blokları kayıtta tek soru tanımına dönüşür", () => {
        const soru: Tanim = {
            sayfaBasligi: "Anket",
            adimlar: [
                {
                    id: "secim",
                    tur: "soru",
                    soru: "Kimlerle kullanacaksın?",
                    secenekler: [
                        { id: "aile", metin: "Ailemle" },
                        { id: "arkadas", metin: "Arkadaşlarımla" },
                    ],
                },
            ],
        };
        const puck = tanimdanPuck(soru, VARSAYILAN);
        const temelBlok = puck.content[0]!;
        for (const [tur, secimTuru] of [
            ["TekSecim", "tekli"],
            ["CokluSecim", "coklu"],
            ["SerbestYanit", "metin"],
        ] as const) {
            const blok = {
                ...temelBlok,
                type: tur,
                props: { ...temelBlok.props, secimTuru },
            } as HuniVerisi["content"][number];
            const kayitli = puckTanim({ ...puck, content: [blok] }).adimlar[0];
            expect(kayitli).toMatchObject({ id: "secim", tur: "soru" });
            if (secimTuru === "tekli") {
                expect(kayitli).not.toHaveProperty("secimTuru");
            } else {
                expect(kayitli).toHaveProperty("secimTuru", secimTuru);
            }
        }
    });

    it("Puck bileşen kimlikleri benzersiz, adım kimliği ayrı alanda", () => {
        const veri = tanimdanPuck(temel, VARSAYILAN);
        const idler = veri.content.map((o) => o.props.id);
        expect(new Set(idler).size).toBe(idler.length);
        expect(veri.content.map((o) => o.props.kimlik)).toStrictEqual([
            "karsilama",
            "kiminle",
            "yorum",
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
