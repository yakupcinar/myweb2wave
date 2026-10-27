import { describe, expect, it } from "vitest";
import { dugmeler, type YayinDurumu } from "./ustCubuk";

const yayin = (farkli: boolean): YayinDurumu => ({
    surumler: [{ surum: 1, zaman: "2026-10-21 09:00:00+00", ziyaret: 3 }],
    farkli,
    onizleme: "https://huni.test/keepchat/?huni=genel&onizleme=x",
    yayindaki: "https://huni.test/keepchat/?huni=genel",
});

describe("üst çubuk", () => {
    it("kaydedilmiş ve yayından farklı: hepsi açık", () => {
        expect(dugmeler(yayin(true), false, false)).toMatchObject({
            kaydet: true,
            onizle: true,
            yayinla: true,
            geriYukle: true,
            not: "Kaydedildi, yayında değil",
            durum: "taslak",
        });
    });
    it("kaydedilmemiş değişiklik: yalnız Kaydet (önizleme/yayın kaydedileni gösterir)", () => {
        expect(dugmeler(yayin(true), true, false)).toEqual({
            kaydet: true,
            onizle: false,
            yayinla: false,
            geriYukle: false,
            not: "Kaydedilmemiş değişiklik — önce kaydet",
            durum: "kaydedilmemis",
        });
    });
    it("yayındakiyle aynı: Yayınla kapalı, önizleme açık", () => {
        expect(dugmeler(yayin(false), false, false)).toMatchObject({
            onizle: true,
            yayinla: false,
            not: "Yayındakiyle aynı",
            durum: "yayinlandi",
        });
    });
    it("istek sürerken ya da durum alınamadıysa yayın düğmeleri kapalı", () => {
        for (const d of [
            dugmeler(yayin(true), false, true),
            dugmeler(null, false, false),
        ]) {
            expect([d.onizle, d.yayinla, d.geriYukle]).toEqual([
                false,
                false,
                false,
            ]);
        }
        expect(dugmeler(yayin(true), false, true).kaydet).toBe(false);
        expect(dugmeler(yayin(true), false, true).durum).toBe("mesgul");
        expect(dugmeler(null, false, false).durum).toBe("bilinmiyor");
    });
});
