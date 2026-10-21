import { describe, expect, it } from "vitest";
import css from "./huni.css?raw";
import ornekler from "./tema-ornekleri.json";
import {
    anaYazi,
    kontrast,
    temaDegiskenleri,
    VARSAYILAN_TEMA,
    YAZI_TIPLERI,
} from "./tema";

// Aynı örnekleri core/tests/Huni/TemaTest.php de okur: iki dilin formülü
// ve sabitleri birbirinden kayarsa ikisinden biri kırmızı olur.
describe("tema — çekirdekle ortak örnekler", () => {
    it("varsayılan tema ve yazı tipleri Tema.php ile aynı", () => {
        expect(VARSAYILAN_TEMA).toEqual(ornekler.varsayilan);
        expect(Object.keys(YAZI_TIPLERI)).toEqual(ornekler.yaziTipleri);
    });

    it.each(ornekler.kontrast)("kontrast($a, $b) = $oran", ({ a, b, oran }) => {
        expect(kontrast(a, b)).toBeCloseTo(oran, 2);
        expect(kontrast(b, a)).toBeCloseTo(oran, 2);
    });
});

describe("tema — düğme yazısı", () => {
    // Bağımsız: koyu mavi üzerinde beyaz, sarı üzerinde siyah okunur
    // (parlaklık 0,19 ve 0,93) — formülden değil renkten (tuzak §3).
    it.each([
        ["#2563eb", "#ffffff"],
        ["#000000", "#ffffff"],
        ["#ffff00", "#000000"],
        ["#ffffff", "#000000"],
    ])("%s → %s", (ana, yazi) => {
        expect(anaYazi(ana)).toBe(yazi);
    });
});

describe("huni.css", () => {
    // Puck ana sayfanın stillerini önizleme iframe'ine kopyalıyor: kapsamsız
    // bir kural (ör. "button") düzenleyicinin kendi düğmelerini boyar.
    it("her kural .huni altında (:root yalnız değişken)", () => {
        const seciciler = [...css.matchAll(/([^{}]+)\{/g)].flatMap((m) =>
            (m[1] ?? "")
                .replace(/\/\*[\s\S]*?\*\//g, "")
                .split(",")
                .map((x) => x.trim()),
        );
        expect(seciciler.length).toBeGreaterThan(5);
        for (const s of seciciler) {
            if (s !== ":root") expect(s).toMatch(/^\.huni\b/);
        }
    });

    // Tema yokken huni sayfası değişken yazmaz, :root'takiler geçerli;
    // panel ise varsayılan temayı temaDegiskenleri ile yazar. İkisi aynı olmalı.
    it(":root varsayılanları = temaDegiskenleri(VARSAYILAN_TEMA)", () => {
        const kok = /:root\s*\{([^}]*)\}/.exec(css)?.[1] ?? "";
        const okunan = Object.fromEntries(
            [...kok.matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)].map((m) => [
                m[1],
                m[2]?.trim(),
            ]),
        );
        expect(okunan).toEqual(temaDegiskenleri(VARSAYILAN_TEMA));
    });
});
