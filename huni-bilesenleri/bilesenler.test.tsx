import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { Logo, Paywall, Soru, Tanitim, Yorum } from "./bilesenler";
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
const soru = (aciklama = ""): string =>
    renderToStaticMarkup(
        <Soru
            soru="Kiminle?"
            aciklama={aciklama}
            secenekler={[
                { metin: "Ailemle", gorsel: G },
                { metin: "İş", gorsel: "" },
            ]}
            medyaKoku={KOK}
        />,
    );
const paywall = (): string =>
    renderToStaticMarkup(
        <Paywall
            baslik="Pro"
            metin="9,99 €"
            dugme="Abone ol"
            faydalar={["Sınırsız sohbet", "Reklamsız"]}
            sartlar="https://ornek.com/sartlar"
            gizlilik="https://ornek.com/gizlilik"
            iptal="https://ornek.com/iptal"
        />,
    );
const serbestYanit = (yanitZorunlu = false): string =>
    renderToStaticMarkup(
        <Soru
            soru="Hedefiniz nedir?"
            secimTuru="metin"
            yanitZorunlu={yanitZorunlu}
            devamDugmesi="Devam et"
            medyaKoku=""
        />,
    );
const yorum = (): string =>
    renderToStaticMarkup(
        <Yorum
            baslik="Deneyim"
            metin="Çok faydalı"
            yazar="Ayşe"
            unvan="Kullanıcı"
            dugme="Devam"
        />,
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
                    aciklama={kotu}
                    secenekler={[{ metin: kotu }]}
                    medyaKoku=""
                />,
            ),
            renderToStaticMarkup(
                <Paywall
                    baslik={kotu}
                    metin={kotu}
                    dugme={kotu}
                    faydalar={[kotu]}
                />,
            ),
            renderToStaticMarkup(
                <Yorum
                    baslik={kotu}
                    metin={kotu}
                    yazar={kotu}
                    unvan={kotu}
                    dugme={kotu}
                />,
            ),
        ].join("");
        expect(html).not.toContain("<b>");
        expect(html).not.toContain("<img src=x");
        expect(html.split("&lt;b&gt;x&lt;/b&gt;").length - 1).toBe(15);
    });
});

describe("Ç61 — paywall faydaları ve yasal bağlantılar", () => {
    it("faydalar liste, bağlantılar yeni sekmede ve sayfaya erişimsiz", () => {
        const html = paywall();
        expect(html).toContain(
            '<ul class="faydalar"><li>Sınırsız sohbet</li><li>Reklamsız</li></ul>',
        );
        expect(html).toContain(
            '<a href="https://ornek.com/sartlar" target="_blank" rel="noopener noreferrer">Kullanım şartları</a>',
        );
        expect(html).toContain('href="https://ornek.com/gizlilik"');
        expect(html).toContain('href="https://ornek.com/iptal"');
        expect(html).toContain(">Gizlilik politikası</a>");
        expect(html).toContain(">İptal ve iade</a>");
    });
    it("boşsa liste ve bağlantı satırı hiç yok", () => {
        const html = renderToStaticMarkup(
            <Paywall baslik="Pro" metin="9,99 €" dugme="Abone ol" />,
        );
        expect(html).not.toContain("faydalar");
        expect(html).not.toContain("yasal");
        expect(html).not.toContain("<a ");
    });
    it("yalnız verilen bağlantı çizilir", () => {
        const html = renderToStaticMarkup(
            <Paywall
                baslik="Pro"
                metin=""
                dugme="Al"
                gizlilik="https://ornek.com/g"
            />,
        );
        expect(html.match(/<a /g)).toHaveLength(1);
    });
});

describe("D2 — görsel adresi kimlikten", () => {
    it("logo çekirdeğin medya adresinden, .logo sınıfıyla (Ç61)", () => {
        expect(
            renderToStaticMarkup(<Logo gorsel={G} medyaKoku={KOK} />),
        ).toContain(
            `<img class="logo" src="${KOK}/medya/${G}.webp" alt="" decoding="async"/>`,
        );
    });
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
        for (const html of [tanitim(), soru(), yorum(), paywall()]) {
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
    it("yorum metnini kaynak adıyla alıntı olarak gösterir ve devam eder", () => {
        expect(yorum()).toContain('<blockquote class="yorum-alinti">');
        expect(yorum()).toContain("<strong>Ayşe</strong> · Kullanıcı");
        expect(yorum()).toContain('<button type="button" class="ana">Devam');
    });
    it("sorudaki ek açıklama varsa gösterir, boşsa ek satır üretmez", () => {
        expect(soru("Birden fazla seçenek seçebilirsin.")).toContain(
            '<p class="yardim-metni">Birden fazla seçenek seçebilirsin.</p>',
        );
        expect(soru()).not.toContain("yardim-metni");
    });
    it("serbest yanıt alanı 500 karakterle sınırlı, boşken isteğe bağlı gelir", () => {
        const html = serbestYanit();
        expect(html).toContain('maxLength="500"');
        expect(html).toContain("Yanıtınız (isteğe bağlı)");
        expect(html).toContain('class="ana">Devam et</button>');
        expect(html).not.toContain('class="secenekler"');
        expect(serbestYanit(true)).toContain('required=""');
    });
    it("masaüstünde sütun kart olur: üst boşluk + kenar (Ç61)", () => {
        const masaustu =
            css.match(/@media \(min-width: 768px\)\s*\{([\s\S]*?)\n\}/)?.[1] ??
            "";
        expect(masaustu).toMatch(/\.huni\s*\{[^}]*margin-top:/);
        expect(masaustu).toMatch(/\.huni\s*\{[^}]*border:/);
    });
    it("gizlilik bağlantısı okunur, geri düğmesi boyanır (Ç61)", () => {
        // Dokunma alanı en az 44 px (WCAG 2.5.5 / Apple HIG), yazı ≥ 15 px.
        const blok = (secici: string): string =>
            css.match(new RegExp(`${secici}\\s*\\{([^}]*)\\}`))?.[1] ?? "";
        const izin = blok("\\.huni button\\.izin-ayarlari");
        expect(
            Number(izin.match(/min-height:\s*(\d+)px/)?.[1]),
        ).toBeGreaterThanOrEqual(44);
        expect(
            Number(izin.match(/font-size:\s*(\d+)px/)?.[1]),
        ).toBeGreaterThanOrEqual(15);
        const geri = blok("\\.huni button\\.geri");
        expect(
            Number(geri.match(/min-height:\s*(\d+)px/)?.[1]),
        ).toBeGreaterThanOrEqual(44);
    });
    it("bileşenlerin her sınıfı huni.css'te .huni altında boyanır", () => {
        const siniflar = new Set(
            Array.from(
                [
                    tanitim(G),
                    soru("Kısa açıklama"),
                    serbestYanit(),
                    yorum(),
                    paywall(),
                    renderToStaticMarkup(<Logo gorsel={G} medyaKoku="" />),
                ]
                    .join("")
                    .matchAll(/class="([^"]+)"/g),
            ).flatMap((m) => (m[1] ?? "").split(" ")),
        );
        expect([...siniflar].sort()).toEqual([
            "ana",
            "faydalar",
            "kapak",
            "logo",
            "secenek-gorseli",
            "secenekler",
            "serbest-yanit",
            "yardim-metni",
            "yasal",
            "yorum-alinti",
        ]);
        for (const s of siniflar) {
            // Sınıf seçicinin ÖZNESİ olmalı: ".huni .secenekler button" kuralı
            // .secenekler'in kendi düzenini (grid) boyamaz (kırma K10).
            expect(css).toMatch(new RegExp(`\\.huni [^{,]*\\.${s}\\s*[{,]`));
        }
    });
});

describe("çoklu seçim", () => {
    it("seçim durumunu erişilebilir aktarır ve hiç seçim yokken devamı kapatır", () => {
        const html = renderToStaticMarkup(
            <Soru
                soru="Neler önemli?"
                secenekler={[{ metin: "Hız" }, { metin: "Fiyat" }]}
                secimTuru="coklu"
                devamDugmesi="Seçimleri gönder"
                medyaKoku=""
            />,
        );
        expect(html).toContain('aria-pressed="false"');
        expect(html).toContain(
            '<button type="button" class="ana" disabled="">Seçimleri gönder</button>',
        );
        expect(css).toContain('.huni .secenekler button[aria-pressed="true"]');
    });
});
