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
    const soru = yapilandirma(ortam).components.Soru;
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
                    content: [{ type: "Soru", props: { id: "soru-1" } }],
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
            yapilandirma(ortam).components.Soru.fields?.aciklama?.label,
        ).toBe("Açıklama (isteğe bağlı)");
    });

    it("serbest yanıt panel seçeneği isteğe bağlı gelir", () => {
        const soru = yapilandirma(ortam).components.Soru;
        const secimTuru = soru.fields?.secimTuru;
        expect(
            secimTuru && "options" in secimTuru ? secimTuru.options : [],
        ).toContainEqual({ label: "Serbest yanıt", value: "metin" });
        expect(soru.defaultProps?.yanitZorunlu).toBe("istege_bagli");
    });
});
