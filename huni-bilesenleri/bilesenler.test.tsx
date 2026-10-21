import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { Paywall, Soru, Tanitim } from "./bilesenler";
import css from "./huni.css?raw";

const G = "a".repeat(64);
const KOK = "https://cekirdek.ornek";

const tanitim = (gorsel?: string): string =>
    renderToStaticMarkup(
        <Tanitim
            baslik="Merhaba"
            metin="Kısa"
            dugme="Başla"
            gorsel={gorsel}
            medyaKoku={KOK}
        />,
    );
const soru = (): string =>
    renderToStaticMarkup(
        <Soru
            soru="Kiminle?"
            secenekler={[
                { metin: "Ailemle", gorsel: G },
                { metin: "İş", gorsel: "" },
            ]}
            medyaKoku={KOK}
        />,
    );
const paywall = (): string =>
    renderToStaticMarkup(
        <Paywall baslik="Pro" metin="9,99 €" dugme="Abone ol" />,
    );

describe("D1 — metin HTML olarak yorumlanmaz", () => {
    it("başlık, metin, seçenek ve düğmedeki etiket kaçışlanır", () => {
        const kotu = "<b>x</b><img src=x onerror=alert(1)>";
        const html = [
            renderToStaticMarkup(
                <Tanitim
                    baslik={kotu}
                    metin={kotu}
                    dugme={kotu}
                    medyaKoku=""
                />,
            ),
            renderToStaticMarkup(
                <Soru
                    soru={kotu}
                    secenekler={[{ metin: kotu }]}
                    medyaKoku=""
                />,
            ),
            renderToStaticMarkup(
                <Paywall baslik={kotu} metin={kotu} dugme={kotu} />,
            ),
        ].join("");
        expect(html).not.toContain("<b>");
        expect(html).not.toContain("<img src=x");
        expect(html.split("&lt;b&gt;x&lt;/b&gt;").length - 1).toBe(8);
    });
});

describe("D2 — görsel adresi kimlikten", () => {
    it("tanıtım kapağı çekirdeğin medya adresinden, alt boş", () => {
        expect(tanitim(G)).toContain(
            `<img class="kapak" src="${KOK}/medya/${G}.webp" alt="" decoding="async"/>`,
        );
    });
    it("görsel yoksa (alan yok ya da boş dize) img yok", () => {
        expect(tanitim()).not.toContain("<img");
        expect(tanitim("")).not.toContain("<img");
    });
    it("yalnız görseli olan seçenekte img", () => {
        const html = soru();
        expect(html.match(/<img /g)?.length).toBe(1);
        expect(html).toContain(
            `<img class="secenek-gorseli" src="${KOK}/medya/${G}.webp"`,
        );
    });
});

describe("DOM huni sayfasıyla aynı", () => {
    it("başlık odaklanabilir (D11), düğmeler type=button", () => {
        for (const html of [tanitim(), soru(), paywall()]) {
            expect(html).toContain('<h1 tabindex="-1">');
            expect(html).not.toMatch(/<button(?![^>]*type="button")/);
        }
    });
    it("tanıtım ve ödeme düğmesi ana, seçenekler .secenekler içinde", () => {
        expect(tanitim()).toContain('<button type="button" class="ana">Başla');
        expect(paywall()).toContain(
            '<button type="button" class="ana">Abone ol',
        );
        expect(soru()).toMatch(
            /<div class="secenekler"><button type="button">.*<span>Ailemle<\/span>/,
        );
    });
    it("bileşenlerin her sınıfı huni.css'te .huni altında boyanır", () => {
        const siniflar = new Set(
            Array.from(
                [tanitim(G), soru(), paywall()]
                    .join("")
                    .matchAll(/class="([^"]+)"/g),
            ).flatMap((m) => (m[1] ?? "").split(" ")),
        );
        expect([...siniflar].sort()).toEqual([
            "ana",
            "kapak",
            "secenek-gorseli",
            "secenekler",
        ]);
        for (const s of siniflar) {
            // Sınıf seçicinin ÖZNESİ olmalı: ".huni .secenekler button" kuralı
            // .secenekler'in kendi düzenini (grid) boyamaz (kırma K10).
            expect(css).toMatch(new RegExp(`\\.huni [^{,]*\\.${s}\\s*[{,]`));
        }
    });
});
