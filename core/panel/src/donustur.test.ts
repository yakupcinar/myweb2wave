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

// T1c: içerik adımı ekran (tanıtım, soru, yorum yok).
const temel: Tanim = {
    sayfaBasligi: "KeepChat Pro",
    adimlar: [
        {
            id: "karsilama",
            tur: "ekran",
            baslik: "Merhaba",
            bloklar: [{ tur: "metin", metin: "Kısa" }],
            dugme: "Başla",
        },
        {
            id: "kiminle",
            tur: "ekran",
            baslik: "Kiminle?",
            bloklar: [
                {
                    tur: "soru",
                    secenekler: [
                        { id: "aile", metin: "Ailemle" },
                        { id: "is", metin: "İş" },
                    ],
                },
            ],
        },
        {
            id: "yorum",
            tur: "ekran",
            baslik: "Deneyim",
            bloklar: [
                {
                    tur: "yorum",
                    metin: "Çok faydalı",
                    yazar: "Ayşe",
                    unvan: "Kullanıcı",
                },
            ],
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
/** İkinci ekranın soru bloğunu değiştirir (eski soru adımı testleri). */
type Blok = Extract<
    Tanim["adimlar"][number],
    { tur: "ekran" }
>["bloklar"][number];
const soruyla = (
    soru: Blok,
    ek: { dugme?: string; onceki?: Blok[] } = {},
): Tanim => ({
    ...temel,
    adimlar: temel.adimlar.map((a) =>
        a.id === "kiminle"
            ? {
                  id: "kiminle",
                  tur: "ekran" as const,
                  baslik: "Kiminle?",
                  bloklar: [...(ek.onceki ?? []), soru],
                  ...(ek.dugme ? { dugme: ek.dugme } : {}),
              }
            : a,
    ),
});

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

    it("çoklu seçimde seçenek dalları kayıtta kaldırılır", () => {
        const secenekler = [
            { id: "aile", metin: "Ailemle", hedef: "paywall" },
            { id: "is", metin: "İş", hedef: "paywall" },
        ];
        const coklu = soruyla(
            { tur: "soru", secimTuru: "coklu", secenekler },
            { dugme: "Seçimleri gönder" },
        );
        expect(puckTanim(tanimdanPuck(coklu, VARSAYILAN))).toStrictEqual(
            soruyla(
                {
                    tur: "soru",
                    secimTuru: "coklu",
                    secenekler: secenekler.map(({ id, metin }) => ({
                        id,
                        metin,
                    })),
                },
                { dugme: "Seçimleri gönder" },
            ),
        );
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
                    tur: "ekran",
                    baslik: "Merhaba",
                    gorsel: G1,
                    bloklar: [],
                    dugme: "Başla",
                    hedef: "kiminle",
                },
                {
                    id: "kiminle",
                    tur: "ekran",
                    baslik: "Kiminle?",
                    bloklar: [
                        {
                            tur: "metin",
                            metin: "Birden fazla seçenek seçebilirsin.",
                        },
                        {
                            tur: "soru",
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
                    ],
                },
                temel.adimlar.at(-1)!,
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

    it("Puck bileşen kimlikleri benzersiz, adım kimliği ayrı alanda", () => {
        const veri = tanimdanPuck(temel, VARSAYILAN);
        const idler = veri.content.map((o) => o.props.id);
        expect(new Set(idler).size).toBe(idler.length);
        expect(
            veri.content.map((o) => (o.props as { kimlik?: string }).kimlik),
        ).toStrictEqual(["karsilama", "kiminle", "yorum", "paywall"]);
    });

    it("G3 zemin: geçiş ve görsel gidiş-dönüş aynı; 'yok' seçilince tanımdan çıkar", () => {
        const gecis: Tanim = {
            ...temel,
            tema: {
                ...VARSAYILAN,
                zemin: { tur: "gecis", ikinciRenk: "#e0e7ff" },
            },
        };
        expect(puckTanim(tanimdanPuck(gecis, VARSAYILAN))).toStrictEqual(gecis);
        const gorsel: Tanim = {
            ...temel,
            tema: {
                ...VARSAYILAN,
                zemin: { tur: "gorsel", gorsel: G1, karartma: 70 },
            },
        };
        const veri = tanimdanPuck(gorsel, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(gorsel);
        veri.root.props!.karartma = "55" as unknown as number; // Puck sayı alanı dize verebilir
        expect(puckTanim(veri).tema?.zemin).toStrictEqual({
            tur: "gorsel",
            gorsel: G1,
            karartma: 55,
        });
        veri.root.props!.zeminTuru = "yok";
        expect(puckTanim(veri).tema).toStrictEqual(VARSAYILAN);
    });

    it("G6 kart: gidiş-dönüş aynı; 'yok' seçilince tanımdan çıkar", () => {
        const kartli: Tanim = {
            ...temel,
            tema: { ...VARSAYILAN, kartRengi: "#ffffff" },
        };
        const veri = tanimdanPuck(kartli, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(kartli);
        veri.root.props!.kart = "yok";
        expect(puckTanim(veri).tema).toStrictEqual(VARSAYILAN);
    });

    it("G7 ön ayarlar: varsayılan dışı değerler gidiş-dönüş aynı; varsayılan yazılmaz", () => {
        const ayarli: Tanim = {
            ...temel,
            tema: {
                ...VARSAYILAN,
                baslikBoyu: "buyuk",
                yogunluk: "sik",
                hizalama: "orta",
            },
        };
        const veri = tanimdanPuck(ayarli, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(ayarli);
        veri.root.props!.baslikBoyu = "normal";
        veri.root.props!.yogunluk = "normal";
        veri.root.props!.hizalama = "sol";
        expect(puckTanim(veri).tema).toStrictEqual(VARSAYILAN);
    });

    it("G5 kapak düzeni (ekranda): gidiş-dönüş aynı; ust yazılmaz", () => {
        const duzenli: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((a, i) =>
                i === 0 && a.tur === "ekran"
                    ? { ...a, gorsel: G1, duzen: "tamEkran" as const }
                    : a,
            ),
        };
        const veri = tanimdanPuck(duzenli, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(duzenli);
        (veri.content[0]!.props as { duzen: string }).duzen = "ust";
        expect("duzen" in puckTanim(veri).adimlar[0]!).toBe(false);
    });

    it("G4: düğme biçimi ve soru düzeni gidiş-dönüş; varsayılan yazılmaz", () => {
        const g4: Tanim = {
            ...soruyla({
                tur: "soru",
                duzen: "izgara",
                secenekler: [
                    { id: "aile", metin: "Ailemle" },
                    { id: "is", metin: "İş" },
                ],
            }),
            tema: { ...VARSAYILAN, dugmeBicimi: "hap" },
        };
        const veri = tanimdanPuck(g4, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(g4);
        veri.root.props!.dugmeBicimi = "dolu";
        expect(puckTanim(veri).tema).toStrictEqual(VARSAYILAN);
    });

    it("seçenek simgesi gidiş-dönüş; boş simge yazılmaz", () => {
        const simgeli = soruyla({
            tur: "soru",
            secenekler: [
                { id: "aile", metin: "Ailemle", simge: "💼" },
                { id: "is", metin: "İş" },
            ],
        });
        expect(puckTanim(tanimdanPuck(simgeli, VARSAYILAN))).toStrictEqual(
            simgeli,
        );
    });

    it("Mobil görünüm 4: yükleme adımı kendi bloğuyla gidiş-dönüş (ödemeye dönüşmez)", () => {
        const yuklemeli: Tanim = {
            ...temel,
            adimlar: [
                ...temel.adimlar.slice(0, -1),
                {
                    id: "hazirlik",
                    tur: "yukleme",
                    baslik: "Planın hazırlanıyor",
                    maddeler: ["Cevapların inceleniyor", "Plan seçiliyor"],
                    sure: 5,
                },
                { id: "kisa", tur: "yukleme", baslik: "Bir saniye" },
                ...temel.adimlar.slice(-1),
            ],
        };
        const veri = tanimdanPuck(yuklemeli, VARSAYILAN);
        expect(veri.content.map((o) => o.type)).toContain("Yukleme");
        expect(puckTanim(veri)).toStrictEqual(yuklemeli);
    });

    it("T1: ekran adımı ve yedi blok türü gidiş-dönüş aynı; boş isteğe bağlı alan yazılmaz", () => {
        const ekranli: Tanim = {
            ...temel,
            adimlar: [
                temel.adimlar[0]!,
                {
                    id: "neden",
                    tur: "ekran",
                    baslik: "Neden KeepChat?",
                    bloklar: [
                        { tur: "gorsel", gorsel: G1, gorselMetni: "Ekran" },
                        { tur: "gorsel", gorsel: G2 },
                        { tur: "baslik", metin: "Ara" },
                        { tur: "metin", metin: "Paragraf" },
                        { tur: "maddeler", maddeler: ["Bir", "İki"] },
                        {
                            tur: "yorum",
                            metin: "İyi",
                            yazar: "Ayşe",
                            unvan: "Öğretmen",
                            puan: 4,
                        },
                        { tur: "yorum", metin: "Güzel", yazar: "Can" },
                        { tur: "istatistik", deger: "%87", metin: "düzenli" },
                        { tur: "kutu", metin: "Güvence", simge: "🔒" },
                        { tur: "kutu", metin: "Not" },
                    ],
                    dugme: "İleri",
                    hedef: "paywall",
                    ilerlemeGoster: false,
                },
                {
                    id: "yalin",
                    tur: "ekran",
                    baslik: "Yalın",
                    bloklar: [{ tur: "metin", metin: "x" }],
                    dugme: "Devam",
                },
                ...temel.adimlar.slice(1),
            ],
        };
        const veri = tanimdanPuck(ekranli, VARSAYILAN);
        const ekran = veri.content[1]!;
        expect(ekran.type).toBe("Ekran");
        // Bloklar yuvada ayrı Puck bileşenleri, kimlikleri benzersiz.
        const bloklar = (ekran.props as { bloklar: unknown }).bloklar as {
            type: string;
            props: { id: string };
        }[];
        expect(bloklar.map((b) => b.type)).toEqual([
            "GorselBlogu",
            "GorselBlogu",
            "BaslikBlogu",
            "MetinBlogu",
            "MaddelerBlogu",
            "YorumBlogu",
            "YorumBlogu",
            "IstatistikBlogu",
            "KutuBlogu",
            "KutuBlogu",
        ]);
        expect(new Set(bloklar.map((b) => b.props.id)).size).toBe(10);
        expect(puckTanim(veri)).toStrictEqual(ekranli);
    });

    it("T1b: soru bloğu (tekli/çoklu/serbest) gidiş-dönüş aynı; düğmesiz ekran düğmesiz kalır", () => {
        const sorulu: Tanim = {
            ...temel,
            adimlar: [
                {
                    id: "tek",
                    tur: "ekran",
                    baslik: "Kiminle?",
                    bloklar: [
                        { tur: "metin", metin: "Seç" },
                        {
                            tur: "soru",
                            duzen: "izgara",
                            secenekler: [
                                {
                                    id: "aile",
                                    metin: "Ailemle",
                                    simge: "👪",
                                    hedef: "paywall",
                                },
                                {
                                    id: "is",
                                    metin: "İş",
                                    gorsel: G1,
                                    gorselMetni: "İş",
                                },
                            ],
                        },
                    ],
                },
                {
                    id: "cok",
                    tur: "ekran",
                    baslik: "Neler?",
                    bloklar: [
                        {
                            tur: "soru",
                            secimTuru: "coklu",
                            secenekler: [
                                { id: "a", metin: "A" },
                                { id: "b", metin: "B" },
                            ],
                        },
                    ],
                    dugme: "Gönder",
                    hedef: "paywall",
                },
                {
                    id: "kapakli",
                    tur: "ekran",
                    baslik: "Hoş geldin",
                    gorsel: G1,
                    gorselMetni: "Kapak",
                    duzen: "tamEkran",
                    bloklar: [{ tur: "metin", metin: "Kısa" }],
                    dugme: "Başla",
                },
                {
                    id: "kapak-ust",
                    tur: "ekran",
                    baslik: "Üstte",
                    gorsel: G2,
                    bloklar: [{ tur: "metin", metin: "Kısa" }],
                    dugme: "Başla",
                },
                {
                    id: "yazi",
                    tur: "ekran",
                    baslik: "Ne düşünüyorsun?",
                    bloklar: [
                        { tur: "soru", secimTuru: "metin", yanitZorunlu: true },
                    ],
                    dugme: "Gönder",
                },
                ...temel.adimlar.slice(-1),
            ],
        };
        const veri = tanimdanPuck(sorulu, VARSAYILAN);
        const ilk = (veri.content[0]!.props as { bloklar: { type: string }[] })
            .bloklar;
        expect(ilk.map((b) => b.type)).toEqual(["MetinBlogu", "SoruBlogu"]);
        expect(puckTanim(veri)).toStrictEqual(sorulu);
    });

    it("T3c: koşullu değerler (ekran + blok) gidiş-dönüş aynı; boşken alan yazılmaz", () => {
        const kural = (alan: string, deger: string) => ({
            kosullar: [
                {
                    ozellik: "utm.source",
                    islem: "esit" as const,
                    deger: "meta",
                },
            ],
            alan,
            deger,
        });
        const degerli: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((a) =>
                a.id === "karsilama" && a.tur === "ekran"
                    ? {
                          ...a,
                          degerler: [
                              {
                                  ...kural("baslik", "Meta"),
                                  baglac: "herhangi",
                              },
                          ],
                          bloklar: [
                              {
                                  tur: "metin",
                                  metin: "Kısa",
                                  degerler: [kural("metin", "Uzun")],
                              },
                          ],
                      }
                    : a,
            ),
        };
        const veri = tanimdanPuck(degerli, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(degerli);
        const ekran = veri.content[0]!.props as {
            degerler: unknown[];
            bloklar: { props: { degerler: unknown[] } }[];
        };
        ekran.degerler = [];
        ekran.bloklar[0]!.props.degerler = [];
        const geri = puckTanim(veri).adimlar[0]!;
        expect("degerler" in geri).toBe(false);
        expect("degerler" in (geri as { bloklar: object[] }).bloklar[0]!).toBe(
            false,
        );
    });

    it("T3b: gösterim koşulu gidiş-dönüş aynı; koşulsuzken alan yazılmaz", () => {
        const kosullu: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((a, i) =>
                i === 2 && a.tur === "ekran"
                    ? {
                          ...a,
                          goster: {
                              baglac: "herhangi",
                              kosullar: [
                                  {
                                      ozellik: "cevap.kiminle",
                                      islem: "icermez",
                                      deger: "aile",
                                  },
                                  { ozellik: "utm.source", islem: "var" },
                              ],
                          },
                      }
                    : a,
            ),
        };
        expect(kosullu.adimlar[2]!.tur).toBe("ekran");
        const veri = tanimdanPuck(kosullu, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(kosullu);
        (
            veri.content[2]!.props as { gosterKosullar: unknown[] }
        ).gosterKosullar = [];
        expect("goster" in puckTanim(veri).adimlar[2]!).toBe(false);
    });

    it("T3a: yönlendirme kuralları gidiş-dönüş aynı; varsayılan bağlaç ve boş değer yazılmaz, sürüm sayı", () => {
        const kurallar: Tanim = {
            ...temel,
            adimlar: temel.adimlar.map((a) =>
                a.id === "kiminle"
                    ? {
                          ...a,
                          yonlendirme: [
                              {
                                  kosullar: [
                                      {
                                          ozellik: "cevap.kiminle",
                                          islem: "icerir",
                                          deger: "aile",
                                      },
                                  ],
                                  hedef: "paywall",
                              },
                              {
                                  baglac: "herhangi",
                                  kosullar: [
                                      {
                                          ozellik: "huni.surum",
                                          islem: "esit",
                                          deger: 2,
                                      },
                                      { ozellik: "izin.reklam", islem: "yok" },
                                  ],
                                  hedef: "yorum",
                              },
                          ],
                      }
                    : a,
            ),
        };
        const veri = tanimdanPuck(kurallar, VARSAYILAN);
        expect(puckTanim(veri)).toStrictEqual(kurallar);
        // Kural silinince alan tanımdan çıkar (boş liste çekirdekte geçersiz).
        (veri.content[1]!.props as { yonlendirme: unknown[] }).yonlendirme = [];
        expect("yonlendirme" in puckTanim(veri).adimlar[1]!).toBe(false);
    });

    it("T1: ekran dışına bırakılan blok tanımda türüyle kalır (çekirdek reddeder, sessizce düşmez)", () => {
        const veri = tanimdanPuck(temel, VARSAYILAN);
        veri.content.splice(1, 0, {
            type: "MetinBlogu",
            props: { id: "MetinBlogu-1", metin: "Kayıp" },
        });
        expect(puckTanim(veri).adimlar[1]).toEqual({
            id: "MetinBlogu-1",
            tur: "MetinBlogu",
        });
        expect(puckTanim(veri).adimlar).toHaveLength(5);
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

describe("paywall faydaları ve yasal bağlantılar (Ç61)", () => {
    const paywall = (ek: object) =>
        ({
            sayfaBasligi: "x",
            adimlar: [
                { id: "g", tur: "ekran", baslik: "G", bloklar: [], dugme: "D" },
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
            { id: "g", tur: "ekran", baslik: "G", bloklar: [], dugme: "D" },
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
    const tanim: Tanim = {
        sayfaBasligi: "x",
        adimlar: [
            {
                id: "t",
                tur: "ekran",
                baslik: "T",
                gorsel: g,
                gorselMetni: "Ekran",
                bloklar: [
                    {
                        tur: "soru",
                        secenekler: [
                            {
                                id: "a",
                                metin: "A",
                                gorsel: g,
                                gorselMetni: "Aile",
                            },
                            { id: "b", metin: "B" },
                        ],
                    },
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
    };
    it("gidiş-dönüşte aynı", () => {
        expect(puckTanim(tanimdanPuck(tanim, VARSAYILAN))).toEqual(tanim);
    });
    it("görsel kaldırılınca alt metin tanıma yazılmaz (çekirdek reddederdi)", () => {
        const veri = tanimdanPuck(tanim, VARSAYILAN);
        const t = veri.content[0]!;
        const tp = t.props as unknown as {
            gorsel: string;
            bloklar: { props: { secenekler: { gorsel: string }[] } }[];
        };
        tp.gorsel = "";
        tp.bloklar[0]!.props.secenekler[0]!.gorsel = "";
        const sonuc = puckTanim(veri);
        expect(sonuc.adimlar[0]).not.toHaveProperty("gorselMetni");
        const ekran = sonuc.adimlar[0] as {
            bloklar: { secenekler: object[] }[];
        };
        expect(ekran.bloklar[0]!.secenekler[0]).not.toHaveProperty(
            "gorselMetni",
        );
    });
});
