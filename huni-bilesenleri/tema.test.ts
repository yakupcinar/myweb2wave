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

describe("tema — zemin (G3)", () => {
    const G = "a".repeat(64);
    it("geçiş: arka plandan ikinci renge, alt çubuk ikinci renge solar", () => {
        const d = temaDegiskenleri({
            ...VARSAYILAN_TEMA,
            zemin: { tur: "gecis", ikinciRenk: "#e0e7ff" },
        });
        expect(d["--zemin"]).toBe("linear-gradient(180deg, #ffffff, #e0e7ff)");
        expect(d["--alt-renk"]).toBe("#e0e7ff");
    });
    it("görsel: arka plan renginde karartma katmanı + medya adresi", () => {
        const d = temaDegiskenleri(
            {
                ...VARSAYILAN_TEMA,
                arkaPlan: "#0b1120",
                zemin: { tur: "gorsel", gorsel: G, karartma: 60 },
            },
            "https://c",
        );
        const katman = "color-mix(in srgb, #0b1120 60%, transparent)";
        expect(d["--zemin"]).toBe(
            `linear-gradient(${katman}, ${katman}), url("https://c/medya/${G}.webp") center / cover no-repeat`,
        );
        expect(d["--alt-renk"]).toBe("#0b1120");
    });
    it("zemin yoksa değişken yazılmaz (huni.css düz arka plana düşer)", () => {
        expect(Object.keys(temaDegiskenleri(VARSAYILAN_TEMA))).not.toContain(
            "--zemin",
        );
        expect(css).toMatch(
            /\.huni \.adimlar \.eylem \{[^}]*var\(--alt-renk, var\(--arka\)\)/,
        );
    });
});

describe("tema — kart (G6)", () => {
    const koyu = {
        ...VARSAYILAN_TEMA,
        arkaPlan: "#1e3a8a",
        metinRengi: "#0f172a",
        kartRengi: "#ffffff",
    };
    it("kart: iç yüzeyler kart rengiyle (--arka), zemin arka plan rengi", () => {
        const d = temaDegiskenleri(koyu);
        expect(d["--arka"]).toBe("#ffffff");
        expect(d["--kart"]).toBe("#ffffff");
        expect(d["--alt-renk"]).toBe("#ffffff");
        expect(d["--zemin"]).toBe("#1e3a8a");
        expect(d["--kart-golge"]).toBeTruthy();
    });
    it("kart + geçiş: geçiş arka plan renginden başlar", () => {
        const d = temaDegiskenleri({
            ...koyu,
            zemin: { tur: "gecis", ikinciRenk: "#312e81" },
        });
        expect(d["--zemin"]).toBe("linear-gradient(180deg, #1e3a8a, #312e81)");
        expect(d["--arka"]).toBe("#ffffff");
        expect(d["--alt-renk"]).toBe("#ffffff");
    });
    it("kartsız: kart değişkeni yok; zemin sayfa kökünde, .huni saydam", () => {
        expect(Object.keys(temaDegiskenleri(VARSAYILAN_TEMA))).not.toContain(
            "--kart",
        );
        expect(css).toMatch(
            /html:has\(> body\.huni\) \{[^}]*background: var\(--zemin, var\(--arka\)\)/,
        );
        expect(css).toMatch(
            /\.huni \{[^}]*background: var\(--kart, transparent\)/,
        );
        expect(css).toMatch(
            /\.huni \{[^}]*box-shadow: var\(--kart-golge, none\)/,
        );
        // Kartta yanlarda zemin görünür; kartsız genişlik değişmez.
        expect(css).toMatch(
            /\.huni \{[^}]*max-width: min\(520px, calc\(100% - 32px - 2 \* var\(--kart-bosluk, 0px\)\)\)/,
        );
    });
});

describe("tema — yerleşim ön ayarları (G7)", () => {
    it("tanımlı ön ayar değişkene dönüşür", () => {
        const d = temaDegiskenleri({
            ...VARSAYILAN_TEMA,
            baslikBoyu: "buyuk",
            yogunluk: "ferah",
            hizalama: "orta",
        });
        expect([
            d["--baslik-boyu"],
            d["--baslik-agirlik"],
            d["--ara"],
            d["--hiza"],
        ]).toEqual(["36px", "850", "1.4", "center"]);
        const k = temaDegiskenleri({
            ...VARSAYILAN_TEMA,
            baslikBoyu: "kucuk",
            yogunluk: "sik",
            hizalama: "sol",
        });
        expect([
            k["--baslik-boyu"],
            k["--baslik-agirlik"],
            k["--ara"],
            k["--hiza"],
        ]).toEqual(["24px", "700", "0.75", "left"]);
    });
    it("ön ayar yoksa değişken yazılmaz; CSS yedeği bugünkü değerler", () => {
        const d = temaDegiskenleri(VARSAYILAN_TEMA);
        for (const ad of [
            "--baslik-boyu",
            "--baslik-agirlik",
            "--ara",
            "--hiza",
        ])
            expect(Object.keys(d)).not.toContain(ad);
        expect(css).toMatch(
            /\.huni h1 \{[^}]*font-size: var\(--baslik-boyu, 28px\)/,
        );
        expect(css).toMatch(
            /\.huni h1 \{[^}]*font-weight: var\(--baslik-agirlik, 750\)/,
        );
        expect(css).toMatch(/\.huni h1 \{[^}]*text-align: var\(--hiza, left\)/);
        expect(css).toMatch(
            /\.huni section > p \{[^}]*text-align: var\(--hiza, left\)/,
        );
        expect(css).toMatch(
            /\.huni \.secenekler \{[^}]*gap: calc\(12px \* var\(--ara, 1\)\)/,
        );
        expect(css).toMatch(
            /\.huni \.secenekler button \{[^}]*min-height: calc\(60px \* var\(--ara, 1\)\)/,
        );
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
    // Tek istisna (G6): html:has(> body.huni) — yalnız gövdesi .huni olan
    // huni sayfasında eşleşir; Puck'ta .huni bir div, düzenleyici boyanmaz.
    it("her kural .huni altında (:root yalnız değişken)", () => {
        // @keyframes içi (from/to) seçici değil; @media başlığı atlanır, içi denetlenir.
        const kurallar = css
            .replace(/@keyframes[^{]+\{(?:[^{}]*\{[^}]*\})*\s*\}/g, "")
            .replace(/@media[^{]+\{/g, "");
        const seciciler = [...kurallar.matchAll(/([^{}]+)\{/g)].flatMap((m) =>
            (m[1] ?? "")
                .replace(/\/\*[\s\S]*?\*\//g, "")
                .split(",")
                .map((x) => x.trim()),
        );
        expect(seciciler.length).toBeGreaterThan(5);
        for (const s of seciciler) {
            if (s !== ":root" && s !== "html:has(> body.huni)")
                expect(s).toMatch(/^\.huni\b/);
        }
    });

    // H4: hareket azaltmayı seçen kullanıcıda animasyon YOK — animasyon
    // yalnız no-preference medya sorgusunun içinde tanımlı olabilir.
    it("adım animasyonu yalnız prefers-reduced-motion: no-preference altında", () => {
        const medya =
            /@media \(prefers-reduced-motion: no-preference\) \{([\s\S]*?\})\s*\}/.exec(
                css,
            );
        expect(medya?.[1]).toMatch(
            /\.huni \[data-gecis\] > \[data-sira\][^}]*animation: huni-adim-gir/,
        );
        const disi = css
            .replace(medya?.[0] ?? "", "")
            .replace(/\/\*[\s\S]*?\*\//g, "");
        expect(disi).not.toMatch(/animation\s*:/);
        expect(disi).toMatch(/@keyframes huni-adim-gir/);
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
