// T2b: oynatıcı sunucuyla aynı özellikleri üretir — örnekler ortak dosyada,
// sunucu tarafı core/tests/Ozellik/OzellikEsligiTest.php.
import { describe, expect, it } from "vitest";
import { ozellikler, type Olay } from "./ozellikler";
import veri from "./ozellik-ornekleri.json";

describe("T2b — özellikler sunucuyla aynı (ortak örnekler)", () => {
    it.each(veri.ornekler.map((o) => [o.ad, o] as const))("%s", (_ad, o) => {
        expect(
            ozellikler({
                huni: o.huni,
                surum: o.surum,
                adres: o.adres,
                izin: o.izin as "evet" | "hayir" | null,
                olaylar: o.olaylar as Olay[],
                deneyler: "deneyler" in o ? o.deneyler : undefined,
            }),
        ).toStrictEqual(o.beklenen);
    });

    it("önizlemede huni/sürüm yoksa o adlar da yok", () => {
        expect(
            ozellikler({ adres: "", izin: null, olaylar: [] }),
        ).toStrictEqual({});
    });
});
