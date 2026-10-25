import { describe, expect, it } from "vitest";
import type { AppState } from "@puckeditor/core";
import { yapilandirma, type Ortam } from "./yapilandirma";

const ortam: Ortam = {
    planlar: ["monthly"],
    yaziTipleri: ["sistem", "serif", "yuvarlak", "mono"],
    medyaAdresi: "",
    jeton: "",
};

async function soruAlanlari(secimTuru: "tekli" | "coklu" | "metin") {
    const soru = yapilandirma(ortam).components.TekSecim;
    const cozumle = soru.resolveFields;
    if (!cozumle) throw new Error("Soru alanlarının dinamik çözümleyicisi yok");
    const sonuc = await cozumle(
        { props: { id: "soru-1", secimTuru } } as unknown as Parameters<
            typeof cozumle
        >[0],
        {
            fields: soru.fields,
            appState: {
                data: {
                    content: [{ type: "TekSecim", props: { id: "soru-1" } }],
                },
            } as unknown as AppState,
        } as Parameters<typeof cozumle>[1],
    );
    if (sonuc.secenekler.type !== "array") {
        throw new Error("Soru seçenekleri dizi alanı olmalı");
    }
    return {
        hedef: sonuc.secenekler.arrayFields.hedef,
        devam: sonuc.devamDugmesi,
        zorunlu: sonuc.yanitZorunlu,
        secenekler: sonuc.secenekler,
    };
}

describe("soru ayarlarının görünürlüğü", () => {
    it("tekli soruda hedefleri, çoklu soruda devam yazısını gösterir", async () => {
        const tekli = await soruAlanlari("tekli");
        const coklu = await soruAlanlari("coklu");
        expect(tekli.hedef?.visible).not.toBe(false);
        expect(tekli.devam.visible).toBe(false);
        expect(coklu.hedef?.visible).toBe(false);
        expect(coklu.devam.visible).toBe(true);
        const metin = await soruAlanlari("metin");
        expect(metin.devam.visible).toBe(true);
        expect(metin.zorunlu.visible).toBe(true);
        expect(metin.secenekler.visible).toBe(false);
    });
});

describe("müşteri yorumu bileşeni", () => {
    it("yorum alanlarını Puck düzenlemesine açar", () => {
        const yorum = yapilandirma(ortam).components.Yorum;
        expect(yorum.label).toBe("Müşteri yorumu");
        expect(Object.keys(yorum.fields ?? {})).toEqual(
            expect.arrayContaining([
                "baslik",
                "metin",
                "yazar",
                "unvan",
                "dugme",
            ]),
        );
    });
});

describe("soru içeriği", () => {
    it("isteğe bağlı açıklama alanı düzenlenebilir", () => {
        expect(
            yapilandirma(ortam).components.TekSecim.fields?.aciklama?.label,
        ).toBe("Açıklama (isteğe bağlı)");
    });

    it("serbest yanıt panel seçeneği isteğe bağlı gelir", () => {
        const soru = yapilandirma(ortam).components.TekSecim;
        const secimTuru = soru.fields?.secimTuru;
        expect(
            secimTuru && "options" in secimTuru ? secimTuru.options : [],
        ).toContainEqual({ label: "Serbest yanıt", value: "metin" });
        expect(soru.defaultProps?.yanitZorunlu).toBe("istege_bagli");
    });

    it("menüde genel 'Soru' yok — yalnız üç hazır soru türü (Ç61)", () => {
        const bilesenler = yapilandirma(ortam).components;
        expect(bilesenler).not.toHaveProperty("Soru");
        expect(
            Object.values(bilesenler).filter((b) =>
                String(b?.label).endsWith("sorusu"),
            ),
        ).toHaveLength(3);
    });

    it("eklenen bloğun kimliği benzersiz, yüklenenin kimliği değişmez (Ç61)", async () => {
        const tek = yapilandirma(ortam).components.TekSecim;
        const coz = tek.resolveData;
        if (!coz) throw new Error("ekleme çözümleyicisi yok");
        const ekle = async (id: string, trigger: string) =>
            (
                await coz(
                    {
                        props: { ...tek.defaultProps, id },
                    } as unknown as Parameters<typeof coz>[0],
                    { trigger } as unknown as Parameters<typeof coz>[1],
                )
            ).props?.kimlik;
        const a = await ekle(
            "TekSecim-1f2e3d4c-aaaa-bbbb-cccc-0000000a1b2c",
            "insert",
        );
        const b = await ekle(
            "TekSecim-9e8d7c6b-aaaa-bbbb-cccc-0000000f9e8d",
            "insert",
        );
        expect(a).toBe("tek-secim-sorusu-1b2c");
        expect(a).not.toBe(b);
        // Çekirdek kuralı (Tanim::KIMLIK) burada elle: küçük harf, rakam, - _ (1–64).
        expect(a).toMatch(/^[a-z0-9_-]{1,64}$/);
        expect(
            await ekle("TekSecim-1f2e3d4c-aaaa-bbbb-cccc-0000000a1b2c", "load"),
        ).toBe("tek-secim-sorusu");
    });

    it.each([
        "Tanitim",
        "TekSecim",
        "CokluSecim",
        "SerbestYanit",
        "Yorum",
    ] as const)(
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

    it("soru türleri blok ekleme menüsünde hazır seçenekler olarak görünür", () => {
        const bilesenler = yapilandirma(ortam).components;
        expect(bilesenler.TekSecim.label).toBe("Tek seçim sorusu");
        expect(bilesenler.TekSecim.defaultProps?.secimTuru).toBe("tekli");
        expect(bilesenler.CokluSecim.label).toBe("Çoklu seçim sorusu");
        expect(bilesenler.CokluSecim.defaultProps?.secimTuru).toBe("coklu");
        expect(bilesenler.SerbestYanit.label).toBe("Serbest yanıt sorusu");
        expect(bilesenler.SerbestYanit.defaultProps?.secimTuru).toBe("metin");
    });
});
