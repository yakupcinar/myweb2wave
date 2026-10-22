import { describe, expect, it } from "vitest";
import type { AppState } from "@puckeditor/core";
import { yapilandirma, type Ortam } from "./yapilandirma";

const ortam: Ortam = {
    planlar: ["monthly"],
    yaziTipleri: ["sistem", "serif", "yuvarlak", "mono"],
    medyaAdresi: "",
    jeton: "",
};

async function soruAlanlari(secimTuru: "tekli" | "coklu") {
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
    });
});
