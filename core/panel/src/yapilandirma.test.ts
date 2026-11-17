import { describe, expect, it } from "vitest";
import type { AppState } from "@puckeditor/core";
import { yapilandirma, type Ortam } from "./yapilandirma";

const ortam: Ortam = {
    planlar: ["monthly"],
    yaziTipleri: ["sistem", "serif", "yuvarlak", "mono"],
    hazirTemalar: [],
    medyaAdresi: "",
    jeton: "",
    ozellikler: [
        { ad: "utm.source", aciklama: "reklam kaynağı" },
        { ad: "huni", aciklama: "ziyaretin gördüğü huni" },
    ],
};

// T1c: soru adımı yok — soru, ekranın yuvasındaki soru bloğu.
async function soruAlanlari(secimTuru: "tekli" | "coklu" | "metin") {
    const soru = yapilandirma(ortam).components.SoruBlogu;
    const cozumle = soru.resolveFields;
    if (!cozumle) throw new Error("Soru alanlarının dinamik çözümleyicisi yok");
    const sonuc = await cozumle(
        { props: { id: "SoruBlogu-1", secimTuru } } as unknown as Parameters<
            typeof cozumle
        >[0],
        {
            fields: soru.fields,
            appState: {
                data: {
                    content: [
                        {
                            type: "Ekran",
                            props: {
                                id: "Ekran-1",
                                kimlik: "e",
                                bloklar: [
                                    {
                                        type: "SoruBlogu",
                                        props: { id: "SoruBlogu-1" },
                                    },
                                ],
                            },
                        },
                    ],
                },
            } as unknown as AppState,
        } as Parameters<typeof cozumle>[1],
    );
    if (sonuc.secenekler.type !== "array") {
        throw new Error("Soru seçenekleri dizi alanı olmalı");
    }
    return {
        hedef: sonuc.secenekler.arrayFields.hedef,
        zorunlu: sonuc.yanitZorunlu,
        duzen: sonuc.duzen,
        secenekler: sonuc.secenekler,
    };
}

describe("soru bloğu ayarlarının görünürlüğü", () => {
    it("tekli soruda hedefleri gösterir, çoklu soruda gizler; serbest yanıtta seçenek yok", async () => {
        const tekli = await soruAlanlari("tekli");
        const coklu = await soruAlanlari("coklu");
        expect(tekli.hedef?.visible).not.toBe(false);
        expect(tekli.zorunlu.visible).toBe(false);
        expect(coklu.hedef?.visible).toBe(false);
        const metin = await soruAlanlari("metin");
        expect(metin.zorunlu.visible).toBe(true);
        expect(metin.secenekler.visible).toBe(false);
        expect(metin.duzen.visible).toBe(false);
    });

    it("serbest yanıt seçeneği var ve isteğe bağlı gelir", () => {
        const soru = yapilandirma(ortam).components.SoruBlogu;
        const secimTuru = soru.fields?.secimTuru;
        expect(
            secimTuru && "options" in secimTuru ? secimTuru.options : [],
        ).toContainEqual({ label: "Serbest yanıt", value: "metin" });
        expect(soru.defaultProps?.yanitZorunlu).toBe("istege_bagli");
    });
});

describe("adım menüsü (T1c)", () => {
    it("adım olarak yalnız ekran, yükleme ve ödeme (+ M3b ödeme sonrası ekran); eski türler yok", () => {
        const yap = yapilandirma(ortam);
        expect(yap.categories?.adimlar?.components).toEqual([
            "Ekran",
            "Yukleme",
            "Paywall",
            "OdemeSonrasi",
        ]);
        for (const eski of [
            "Tanitim",
            "TekSecim",
            "CokluSecim",
            "SerbestYanit",
            "Yorum",
        ])
            expect(yap.components).not.toHaveProperty(eski);
    });

    it("yorum bloğu yorum alanlarını Puck düzenlemesine açar", () => {
        const yorum = yapilandirma(ortam).components.YorumBlogu;
        expect(Object.keys(yorum.fields ?? {})).toEqual(
            expect.arrayContaining(["metin", "yazar", "unvan", "puan"]),
        );
    });

    // Ç91: koşullu değerlerde özellik seçenekleri — sunucunun listesi + ekranın
    // ÖNCEKİ ekranlarının cevapları; blokta ekran Puck'ın "parent"ından.
    describe("Ç91: koşullu değer özellikleri", () => {
        const soruEkrani = (
            id: string,
            kimlik: string,
            secimTuru?: string,
        ) => ({
            type: "Ekran",
            props: {
                id,
                kimlik,
                bloklar: [
                    {
                        type: "SoruBlogu",
                        props: { id: `${id}-s`, secimTuru },
                    },
                ],
            },
        });
        // E1: aradaki e-posta ekranının cevabı hiçbir listede yok.
        const appState = {
            data: {
                content: [
                    soruEkrani("E1", "kiminle"),
                    soruEkrani("Ep", "eposta", "eposta"),
                    soruEkrani("E2", "cihaz"),
                    soruEkrani("E3", "son"),
                ],
            },
        } as unknown as AppState;
        const degerOzellikleri = (alanlar: unknown): unknown =>
            (
                alanlar as {
                    degerler: {
                        arrayFields: {
                            kosullar: {
                                arrayFields: {
                                    ozellik: { options: { value: string }[] };
                                };
                            };
                        };
                    };
                }
            ).degerler.arrayFields.kosullar.arrayFields.ozellik.options.map(
                (o) => o.value,
            );
        type Coz = (
            veri: unknown,
            p: { fields: unknown; appState: AppState; parent: unknown },
        ) => unknown;

        it("ekranda: sunucu listesi + önceki ekranların cevabı (kendisininki yok)", () => {
            const ekran = yapilandirma(ortam).components.Ekran;
            const coz = ekran.resolveFields as unknown as Coz;
            expect(
                degerOzellikleri(
                    coz(
                        { props: { id: "E2" } },
                        { fields: ekran.fields, appState, parent: null },
                    ),
                ),
            ).toEqual(["utm.source", "huni", "cevap.kiminle"]);
        });

        it.each([
            "BaslikBlogu",
            "MetinBlogu",
            "GorselBlogu",
            "YorumBlogu",
            "IstatistikBlogu",
            "KutuBlogu",
        ] as const)(
            "%s: bloğun ekranından önceki cevaplar; ekransız blokta yalnız sunucu listesi",
            (tur) => {
                const blok = yapilandirma(ortam).components[tur];
                const coz = blok.resolveFields as unknown as Coz;
                expect(
                    degerOzellikleri(
                        coz(
                            { props: { id: "b" } },
                            {
                                fields: blok.fields,
                                appState,
                                parent: { props: { id: "E3" } },
                            },
                        ),
                    ),
                ).toEqual([
                    "utm.source",
                    "huni",
                    "cevap.kiminle",
                    "cevap.cihaz",
                ]);
                expect(
                    degerOzellikleri(
                        coz(
                            { props: { id: "b" } },
                            { fields: blok.fields, appState, parent: null },
                        ),
                    ),
                ).toEqual(["utm.source", "huni"]);
            },
        );
    });

    it("eklenen ekranın kimliği benzersiz, yüklenenin kimliği değişmez (Ç61)", async () => {
        const ekran = yapilandirma(ortam).components.Ekran;
        const coz = ekran.resolveData;
        if (!coz) throw new Error("ekleme çözümleyicisi yok");
        const ekle = async (id: string, trigger: string) =>
            (
                await coz(
                    {
                        props: { ...ekran.defaultProps, id },
                    } as unknown as Parameters<typeof coz>[0],
                    { trigger } as unknown as Parameters<typeof coz>[1],
                )
            ).props?.kimlik;
        const a = await ekle(
            "Ekran-1f2e3d4c-aaaa-bbbb-cccc-0000000a1b2c",
            "insert",
        );
        const b = await ekle(
            "Ekran-9e8d7c6b-aaaa-bbbb-cccc-0000000f9e8d",
            "insert",
        );
        expect(a).toBe("ekran-1b2c");
        expect(a).not.toBe(b);
        // Çekirdek kuralı (Tanim::KIMLIK) burada elle: küçük harf, rakam, - _ (1–64).
        expect(a).toMatch(/^[a-z0-9_-]{1,64}$/);
        expect(
            await ekle("Ekran-1f2e3d4c-aaaa-bbbb-cccc-0000000a1b2c", "load"),
        ).toBe("ekran");
    });

    it.each(["Ekran", "Yukleme"] as const)(
        "%s eklenince kimliğe benzersiz ek gelir (Ç61)",
        async (tur) => {
            const bilesen = yapilandirma(ortam).components[tur];
            const coz = bilesen.resolveData as unknown as
                | ((
                      veri: { props: Record<string, unknown> },
                      p: { trigger: string },
                  ) => Promise<{ props: { kimlik: string } }>)
                | undefined;
            if (!coz) throw new Error(`${tur}: ekleme çözümleyicisi yok`);
            const sonuc = await coz(
                { props: { ...bilesen.defaultProps, id: `${tur}-0000-9f3a` } },
                { trigger: "insert" },
            );
            expect(sonuc.props.kimlik).toBe(
                `${String(bilesen.defaultProps?.kimlik)}-9f3a`,
            );
        },
    );
});

// V2: hazır tema — seçim kök alanlarını açılıştaki eşlemeyle doldurur, tema
// özel olur, seçim alanı boşalır; bilinmeyen anahtar hiçbir şeyi değiştirmez.
describe("V2: hazır tema seçimi", () => {
    const tema = {
        anaRenk: "#b7a6ff",
        arkaPlan: "#0e1330",
        metinRengi: "#eef0ff",
        yaziTipi: "modern" as const,
        kose: 20,
        kartRengi: "#1a2148",
        zemin: { tur: "gecis" as const, ikinciRenk: "#251a4a" },
        baslikBoyu: "buyuk" as const,
        hizalama: "orta" as const,
    };
    const coz = (hazirTema: string) => {
        const kok = yapilandirma({
            ...ortam,
            hazirTemalar: [
                { anahtar: "gece", ad: "Gece", uygun: "uyku", tema },
            ],
        }).root;
        const resolve = kok?.resolveData as unknown as (v: {
            props: Record<string, unknown>;
        }) => { props: Record<string, unknown> };
        return resolve({
            props: {
                sayfaBasligi: "X",
                ozelTema: "hayir",
                anaRenk: "#2563eb",
                yogunluk: "sik",
                hazirTema,
            },
        }).props;
    };

    it("seçilen temanın alanları yazılır, ön ayar varsayılana döner, seçim boşalır", () => {
        // Beklenen elle (§3): zemin geçiş → zeminTuru/ikinciRenk, kart var.
        expect(coz("gece")).toMatchObject({
            sayfaBasligi: "X",
            ozelTema: "evet",
            hazirTema: "",
            anaRenk: "#b7a6ff",
            arkaPlan: "#0e1330",
            metinRengi: "#eef0ff",
            yaziTipi: "modern",
            kose: 20,
            zeminTuru: "gecis",
            ikinciRenk: "#251a4a",
            kart: "var",
            kartRengi: "#1a2148",
            baslikBoyu: "buyuk",
            hizalama: "orta",
            yogunluk: "normal",
            dugmeBicimi: "dolu",
        });
    });

    it("bilinmeyen ya da boş seçim veriyi değiştirmez", () => {
        expect(coz("")).toMatchObject({
            ozelTema: "hayir",
            anaRenk: "#2563eb",
        });
        expect(coz("yok")).toMatchObject({
            ozelTema: "hayir",
            anaRenk: "#2563eb",
        });
    });
});
