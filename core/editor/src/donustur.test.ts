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

describe("kayıttan gelen soru hazır türle açılır (Ç61)", () => {
    it("yanıt biçimi → TekSecim / CokluSecim / SerbestYanit, gidiş-dönüş aynı", () => {
        const tanim = {
            sayfaBasligi: "x",
            adimlar: [
                {
                    id: "a",
                    tur: "soru",
                    soru: "A?",
                    secenekler: [{ id: "x", metin: "X" }],
                },
                {
                    id: "b",
                    tur: "soru",
                    soru: "B?",
                    secimTuru: "coklu",
                    secenekler: [{ id: "y", metin: "Y" }],
                },
                { id: "c", tur: "soru", soru: "C?", secimTuru: "metin" },
                {
                    id: "p",
                    tur: "paywall",
                    baslik: "P",
                    metin: "",
                    dugme: "Al",
                    plan: "monthly",
                },
            ],
        } as Tanim;
        const puck = tanimdanPuck(tanim, VARSAYILAN);
        expect(puck.content.map((o) => o.type)).toEqual([
            "TekSecim",
            "CokluSecim",
            "SerbestYanit",
            "Paywall",
        ]);
        expect(puckTanim(puck).adimlar).toEqual(tanim.adimlar);
    });
});

describe("paywall faydaları ve yasal bağlantılar (Ç61)", () => {
    const paywall = (ek: object) =>
        ({
            sayfaBasligi: "x",
            adimlar: [
                { id: "g", tur: "tanitim", baslik: "G", metin: "", dugme: "D" },
                {
                    id: "p",
                    tur: "paywall",
                    baslik: "P",
                    metin: "",
                    dugme: "Al",
                    plan: "monthly",
                    ...ek,
                },
            ],
        }) as Tanim;
    it("dolu alanlar gidiş-dönüşte aynı", () => {
        const tanim = paywall({
            faydalar: ["Sınırsız", "Reklamsız"],
            sartlar: "https://ornek.com/s",
            gizlilik: "https://ornek.com/g",
            iptal: "https://ornek.com/i",
        });
        expect(puckTanim(tanimdanPuck(tanim, VARSAYILAN))).toEqual(tanim);
    });
    it("boş alan tanıma hiç yazılmaz (çekirdek boş listeyi reddeder)", () => {
        const veri = tanimdanPuck(paywall({}), VARSAYILAN);
        const p = veri.content[1]!;
        p.props = {
            ...p.props,
            faydalar: [{ metin: "" }],
            sartlar: "",
        } as typeof p.props;
        const adim = puckTanim(veri).adimlar[1]!;
        for (const alan of ["faydalar", "sartlar", "gizlilik", "iptal"])
            expect(adim).not.toHaveProperty(alan);
    });
});

describe("logo (Ç61)", () => {
    const tanim = {
        sayfaBasligi: "x",
        adimlar: [
            { id: "g", tur: "tanitim", baslik: "G", metin: "", dugme: "D" },
            {
                id: "p",
                tur: "paywall",
                baslik: "P",
                metin: "",
                dugme: "Al",
                plan: "monthly",
            },
        ],
    } as Tanim;
    it("logo gidiş-dönüşte aynı; yoksa tanıma yazılmaz", () => {
        const logolu = { ...tanim, logo: "b".repeat(64) } as Tanim;
        expect(puckTanim(tanimdanPuck(logolu, VARSAYILAN))).toEqual(logolu);
        expect(puckTanim(tanimdanPuck(tanim, VARSAYILAN))).not.toHaveProperty(
            "logo",
        );
    });
});

describe("görsel alt metni (Ç44)", () => {
    const g = "d".repeat(64);
    const tanim = {
        sayfaBasligi: "x",
        adimlar: [
            {
                id: "t",
                tur: "tanitim",
                baslik: "T",
                metin: "",
                dugme: "D",
                gorsel: g,
                gorselMetni: "Ekran",
            },
            {
                id: "s",
                tur: "soru",
                soru: "?",
                secenekler: [
                    { id: "a", metin: "A", gorsel: g, gorselMetni: "Aile" },
                    { id: "b", metin: "B" },
                ],
            },
            {
                id: "p",
                tur: "paywall",
                baslik: "P",
                metin: "",
                dugme: "Al",
                plan: "monthly",
            },
        ],
    } as Tanim;
    it("gidiş-dönüşte aynı", () => {
        expect(puckTanim(tanimdanPuck(tanim, VARSAYILAN))).toEqual(tanim);
    });
    it("görsel kaldırılınca alt metin tanıma yazılmaz (çekirdek reddederdi)", () => {
        const veri = tanimdanPuck(tanim, VARSAYILAN);
        const t = veri.content[0]!;
        t.props = { ...t.props, gorsel: "" } as typeof t.props;
        const s = veri.content[1]!;
        const sp = s.props as unknown as { secenekler: { gorsel: string }[] };
        sp.secenekler[0]!.gorsel = "";
        const sonuc = puckTanim(veri);
        expect(sonuc.adimlar[0]).not.toHaveProperty("gorselMetni");
        const soru = sonuc.adimlar[1] as { secenekler: object[] };
        expect(soru.secenekler[0]).not.toHaveProperty("gorselMetni");
    });
});
