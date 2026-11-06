// @vitest-environment happy-dom
// V4: sayan istatistik — parçalara ayırma, binlik ayırıcıyı koruma, ondalığı
// sayamama; bileşen sayılmayacak ortamda son değeri, sayılacakta sıfırdan
// başlayıp son değere varır.
import { act, cleanup, render } from "@testing-library/react";
import { renderToStaticMarkup } from "react-dom/server";
import { afterEach, describe, expect, it, vi } from "vitest";
import { Blok, HareketBaglami } from "./bilesenler";
import { sayiParcalari, sayiYaz } from "./sayac";

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe("V4 — sayı parçaları", () => {
    it.each([
        ["%87", "%", 87, "", ""],
        ["3 dk", "", 3, " dk", ""],
        ["10.000+", "", 10000, "+", "."],
        ["1 250 kişi", "", 1250, " kişi", " "],
        ["24/7", "", 24, "/7", ""],
    ])("%s", (deger, once, sayi, sonra, ayirici) => {
        expect(sayiParcalari(deger)).toEqual({ once, sayi, sonra, ayirici });
    });

    it("ondalık ve sayısız değer sayılmaz", () => {
        expect(sayiParcalari("4,8")).toBeNull();
        expect(sayiParcalari("Ücretsiz")).toBeNull();
    });

    it("yazarken binlik ayırıcı ve önek/sonek korunur", () => {
        const p = sayiParcalari("10.000+")!;
        expect([0, 1234.4, 10000].map((n) => sayiYaz(p, n))).toEqual([
            "0+",
            "1.234+",
            "10.000+",
        ]);
    });
});

const istatistik = (hareket: "yok" | "sakin" | "canli", deger = "10.000+") => (
    <HareketBaglami.Provider value={hareket}>
        <Blok blok={{ tur: "istatistik", deger, metin: "kişi" }} medyaKoku="" />
    </HareketBaglami.Provider>
);

describe("V4 — sayan istatistik", () => {
    it("gözlemcisi olmayan ortamda (sunucu çizimi, eski tarayıcı) son değer", () => {
        vi.stubGlobal("IntersectionObserver", undefined);
        expect(renderToStaticMarkup(istatistik("canli"))).toContain(
            "<strong>10.000+</strong>",
        );
    });

    it("canlı ve hareket serbestken sıfırdan başlar, görününce son değere sayar", async () => {
        let gorundu: ((g: { isIntersecting: boolean }[]) => void) | undefined;
        vi.stubGlobal(
            "IntersectionObserver",
            class {
                constructor(cb: (g: { isIntersecting: boolean }[]) => void) {
                    gorundu = cb;
                }
                observe() {}
                disconnect() {}
            },
        );
        vi.stubGlobal("matchMedia", () => ({ matches: false }));
        const { container } = render(istatistik("canli"));
        const metin = () => container.querySelector("strong")?.textContent;
        expect(metin()).toBe("0+");
        await act(async () => {
            gorundu?.([{ isIntersecting: true }]);
            await new Promise((r) => setTimeout(r, 1100));
        });
        expect(metin()).toBe("10.000+");
    });

    it.each([
        ["sakin", false],
        ["canli", true],
    ] as const)(
        "%s, hareket azaltılmış=%s: son değer, sayma yok",
        (hareket, azalt) => {
            vi.stubGlobal(
                "IntersectionObserver",
                class {
                    observe() {}
                    disconnect() {}
                },
            );
            vi.stubGlobal("matchMedia", () => ({ matches: azalt }));
            const { container } = render(istatistik(hareket));
            expect(container.querySelector("strong")?.textContent).toBe(
                "10.000+",
            );
        },
    );
});
