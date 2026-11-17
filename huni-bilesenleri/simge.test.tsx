// @vitest-environment happy-dom
// V3a: emoji simgesi temanın biçimine göre çizilir; görsel yoksa cihaz
// emojisine döner. Anahtar kuralı çekirdekle ortak örnekten (SimgeTest).
import { cleanup, fireEvent, render } from "@testing-library/react";
import { renderToStaticMarkup } from "react-dom/server";
import { afterEach, describe, expect, it } from "vitest";
import css from "./huni.css?raw";
import { Blok, SimgeBaglami } from "./bilesenler";
import ornekler from "./simge-ornekleri.json";
import { simgeKodu } from "./simge";

const kutu = (stil: "cihaz" | "renkli" | "tekrenk", simge = "☕️") => (
    <SimgeBaglami.Provider value={{ stil, kok: "https://cekirdek.ornek" }}>
        <Blok blok={{ tur: "kutu", metin: "Not", simge }} medyaKoku="" />
    </SimgeBaglami.Provider>
);

afterEach(cleanup);

describe("V3a — simge", () => {
    it("anahtar kuralı çekirdekle aynı örneklerde", () => {
        expect(ornekler.ornekler).toHaveLength(6);
        for (const o of ornekler.ornekler)
            expect(simgeKodu(o.emoji)).toBe(o.kod);
    });

    it("bağlam yoksa ve cihaz biçiminde emoji metin olarak", () => {
        const yalin = renderToStaticMarkup(
            <Blok
                blok={{ tur: "kutu", metin: "Not", simge: "☕️" }}
                medyaKoku=""
            />,
        );
        expect(yalin).toContain(
            '<span class="simge" aria-hidden="true">☕️</span>',
        );
        expect(renderToStaticMarkup(kutu("cihaz"))).toBe(yalin);
    });

    it("renkli: çekirdekteki WebP, FE0F'siz anahtarla; dekoratif", () => {
        const html = renderToStaticMarkup(kutu("renkli"));
        expect(html).toContain(
            '<span class="simge" aria-hidden="true"><img src="https://cekirdek.ornek/simge/renkli/2615.webp" alt=""',
        );
        expect(html).not.toContain("☕");
    });

    it("tekrenk: maske adresi değişkende, aynı adresi gizli img yoklar", () => {
        const html = renderToStaticMarkup(kutu("tekrenk"));
        expect(html).toContain('class="simge simge-tekrenk"');
        expect(html).toContain(
            "--simge:url(&quot;https://cekirdek.ornek/simge/tekrenk/2615.svg&quot;)",
        );
        expect(html).toContain(
            '<img src="https://cekirdek.ornek/simge/tekrenk/2615.svg" alt="" hidden=""',
        );
    });

    it("yoklama img'si gizli kalır (simge img kuralı [hidden]'ı ezmesin)", () => {
        // Ölçüldü: .simge img { display: block } siyah SVG'yi maskenin üstüne çizdi.
        expect(css).toMatch(/\.huni \.simge img\[hidden\] \{\s*display: none;/);
    });

    it.each(["renkli", "tekrenk"] as const)(
        "%s: görsel yüklenemezse cihaz emojisi",
        (stil) => {
            const { container } = render(kutu(stil, "🇹🇷"));
            fireEvent.error(container.querySelector("img")!);
            expect(container.querySelector(".simge")?.outerHTML).toBe(
                '<span class="simge" aria-hidden="true">🇹🇷</span>',
            );
        },
    );
});
