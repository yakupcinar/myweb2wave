import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import {
    Adim,
    Ekran,
    Logo,
    Paywall,
    Soru,
    Tanitim,
    Yorum,
    Yukleme,
} from "./bilesenler";
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
            fiyat="€9,99 / ay"
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
        // Ç19/Ç21: Stripe'tan gelen fiyat faydaların altında, seçili plan
        // kartında; düğme hemen ardından (mobil görünüm).
        expect(html).toContain(
            '</ul><div class="plan-karti"><span class="isaret" aria-hidden="true"></span><p class="fiyat">€9,99 / ay</p></div><div class="eylem">',
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
    it("alt metin verilirse img alt'ında, verilmezse boş (Ç44)", () => {
        expect(
            renderToStaticMarkup(
                <Tanitim
                    baslik="B"
                    metin=""
                    dugme="D"
                    gorsel={G}
                    gorselMetni="Sohbet ekranı"
                    medyaKoku=""
                />,
            ),
        ).toContain('alt="Sohbet ekranı"');
        const html = renderToStaticMarkup(
            <Soru
                soru="?"
                secenekler={[
                    { metin: "A", gorsel: G, gorselMetni: "Aile" },
                    { metin: "B", gorsel: G },
                ]}
                medyaKoku=""
            />,
        );
        expect(html).toContain('alt="Aile"');
        expect(html.match(/alt=""/g)).toHaveLength(1);
    });
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

describe("G4 — seçenek dizilişi ve düğme biçimi", () => {
    const sor = (duzen?: "liste" | "izgara"): string =>
        renderToStaticMarkup(
            <Soru
                soru="Kiminle?"
                secenekler={[{ metin: "Ailemle" }, { metin: "İş" }]}
                duzen={duzen}
                medyaKoku={KOK}
            />,
        );
    it("liste ya da yoksa işaret yok; izgara seçenek kutusunda", () => {
        expect(sor()).not.toContain("data-duzen");
        expect(sor("liste")).not.toContain("data-duzen");
        expect(sor("izgara")).toContain(
            '<div class="secenekler" data-tur="tekli" data-duzen="izgara">',
        );
    });
    it("oynatıcının Adim'i soru düzenini aktarır", () => {
        const html = renderToStaticMarkup(
            <Adim
                adim={{
                    tur: "soru",
                    soru: "Kiminle?",
                    secenekler: [{ metin: "A" }, { metin: "B" }],
                    duzen: "izgara",
                }}
                medyaKoku={KOK}
            />,
        );
        expect(html).toContain('data-duzen="izgara"');
    });
    it("CSS: ızgara iki sütun; ana düğme biçim değişkenlerinden; tam ekranda dolu", () => {
        expect(css).toMatch(
            /\.secenekler\[data-duzen="izgara"\] \{[^}]*grid-template-columns: 1fr 1fr/,
        );
        expect(css).toMatch(
            /\.huni button\.ana,\s*\.huni a\.ana \{[^}]*background: var\(--dugme-arka, var\(--ana\)\)/,
        );
        expect(css).toMatch(
            /\.huni button\.ana,\s*\.huni a\.ana \{[^}]*color: var\(--dugme-yazi, var\(--ana-yazi\)\)/,
        );
        // Dolu düğmenin kenarı eskisi gibi 1px (G4 yalnız çerçevede 2px).
        expect(css).toMatch(
            /\.huni button\.ana,\s*\.huni a\.ana \{[^}]*border-width: var\(--dugme-cizgi, 1px\)/,
        );
        expect(css).toMatch(
            /section\[data-duzen="tamEkran"\] button\.ana \{[^}]*background: var\(--ana\)/,
        );
    });
});

describe("Mobil görünüm 4 — yükleme adımı", () => {
    const html = renderToStaticMarkup(
        <Yukleme
            baslik="Planın hazırlanıyor"
            maddeler={["Cevapların inceleniyor", "Plan seçiliyor"]}
            sure={5}
        />,
    );
    it("başlık, maddeler sırayla, süre değişkende; düğme yok; meşgul duyurulur", () => {
        expect(html).toContain('<section class="yukleme" aria-busy="true"');
        expect(html).toContain("--sure:5s");
        expect(html).toContain('<li style="--i:0">Cevapların inceleniyor</li>');
        expect(html).toContain('<li style="--i:1">Plan seçiliyor</li>');
        expect(html).not.toContain("<button");
    });
    it("süre yoksa 4 sn, madde yoksa liste yok", () => {
        const yalin = renderToStaticMarkup(<Yukleme baslik="Bir saniye" />);
        expect(yalin).toContain("--sure:4s");
        expect(yalin).not.toContain("<ul");
    });
    it("Adim yükleme adımını çizer", () => {
        expect(
            renderToStaticMarkup(
                <Adim
                    adim={{ tur: "yukleme", baslik: "Hazır", sure: 3 }}
                    medyaKoku={KOK}
                />,
            ),
        ).toContain('class="yukleme"');
    });
    it("CSS: çubuk süre boyunca dolar, maddeler sırayla; hareket azaltmada animasyon yok", () => {
        expect(css).toMatch(
            /\.huni \.yukleme-cubuk span \{[^}]*animation: huni-dolum var\(--sure\)/,
        );
        expect(css).toMatch(
            /@media \(prefers-reduced-motion: no-preference\) \{[^@]*huni-dolum/,
        );
    });
});

describe("T1 — ekran adımı (blok kataloğu)", () => {
    const html = renderToStaticMarkup(
        <Ekran
            baslik="Neden KeepChat?"
            bloklar={[
                { tur: "gorsel", gorsel: G, gorselMetni: "Sohbet ekranı" },
                { tur: "metin", metin: "Sohbetlerin tek yerde." },
                { tur: "maddeler", maddeler: ["Yedekleme", "Arama"] },
                {
                    tur: "istatistik",
                    deger: "%87",
                    metin: "düzenli kullanıyor",
                },
                {
                    tur: "yorum",
                    metin: "Çok iyi.",
                    yazar: "Ayşe",
                    unvan: "Öğretmen",
                    puan: 4,
                },
                { tur: "yorum", metin: "Kullanışlı.", yazar: "Can" },
                { tur: "kutu", metin: "İstediğin zaman iptal.", simge: "🔒" },
                { tur: "kutu", metin: "Simgesiz." },
                { tur: "baslik", metin: "İkinci başlık" },
            ]}
            dugme="Devam"
            medyaKoku={KOK}
        />,
    );
    it("ekranın başlığı odaklanan tek h1; başlık bloğu ara başlık (h2)", () => {
        expect(html.match(/<h1 tabindex="-1">/g)).toHaveLength(1);
        expect(html).toContain('<h1 tabindex="-1">Neden KeepChat?</h1>');
        expect(html).toContain("<h2>İkinci başlık</h2>");
    });
    it("bloklar sırayla, düz metin; görsel çekirdekten alt metniyle", () => {
        // React sunucu çizimi görselin önüne preload bağlantısı koyar.
        expect(html).toMatch(
            /<section class="ekran"><h1 tabindex="-1">Neden KeepChat\?<\/h1><div class="bloklar"><img class="blok-gorseli" src="https:\/\/cekirdek\.ornek\/medya\/a{64}\.webp" alt="Sohbet ekranı"/,
        );
        expect(html).toContain("<p>Sohbetlerin tek yerde.</p>");
        expect(html).toContain(
            '<ul class="maddeler"><li>Yedekleme</li><li>Arama</li></ul>',
        );
        expect(html).toContain(
            '<p class="istatistik"><strong>%87</strong> <span>düzenli kullanıyor</span></p>',
        );
    });
    it("yorum: puan yıldızla ve sözle (ekran okuyucu), puansızda yıldız yok", () => {
        expect(html).toContain(
            '<p class="yildizlar" role="img" aria-label="5 üzerinden 4 puan">★★★★☆</p>',
        );
        expect(html.match(/class="yildizlar"/g)).toHaveLength(1);
        expect(html).toContain(
            "<blockquote><p>Çok iyi.</p></blockquote><figcaption><strong>Ayşe</strong> · Öğretmen</figcaption>",
        );
        expect(html).toContain("<figcaption><strong>Can</strong></figcaption>");
    });
    it("kutu: simge süs (ekran okuyucuya gizli), simgesizde öğe yok", () => {
        expect(html).toContain(
            '<p class="kutu"><span class="simge" aria-hidden="true">🔒</span><span>İstediğin zaman iptal.</span></p>',
        );
        expect(html).toContain('<p class="kutu"><span>Simgesiz.</span></p>');
    });
    it("altta tek ana düğme, oynatıcıda tıklanır", () => {
        expect(html).toMatch(
            /<h2>İkinci başlık<\/h2><\/div><div class="eylem"><button type="button" class="ana">Devam<\/button><\/div><\/section>$/,
        );
    });
    it("oynatıcının Adim'i ekranı tanımdan çizer", () => {
        const a = renderToStaticMarkup(
            <Adim
                adim={{
                    tur: "ekran",
                    baslik: "Selam",
                    bloklar: [{ tur: "metin", metin: "Nasılsın?" }],
                    dugme: "İleri",
                }}
                medyaKoku={KOK}
            />,
        );
        expect(a).toContain(
            '<h1 tabindex="-1">Selam</h1><div class="bloklar"><p>Nasılsın?</p></div>',
        );
        expect(a).toContain('class="ana">İleri</button>');
    });
    it("her blok sınıfı huni.css'te .huni altında boyanır", () => {
        const siniflar = new Set(
            Array.from(html.matchAll(/class="([^"]+)"/g)).flatMap((m) =>
                (m[1] ?? "").split(" "),
            ),
        );
        expect([...siniflar].sort()).toEqual([
            "ana",
            "blok-gorseli",
            "blok-yorum",
            "bloklar",
            "ekran",
            "eylem",
            "istatistik",
            "kutu",
            "maddeler",
            "simge",
            "yildizlar",
        ]);
        // "ekran" ve "bloklar" yalnız kapsayıcı: kendileri boyanmaz, kuralları
        // çocuklarını seçer.
        expect(css).toMatch(/\.huni \.ekran [^{]*\{/);
        expect(css).toMatch(/\.huni \.bloklar > p,/);
        // Puck her bloğu ayrı kutuya sarar (tarayıcıda ölçüldü).
        for (const p of ["p", "p.istatistik", "p.kutu"])
            expect(css).toContain(
                `.huni .bloklar > [data-puck-component] > ${p} {`,
            );
        // İlk blok başlığa yapışıyordu (ekran görüntüsünde görüldü).
        expect(css).toMatch(/\.huni \.bloklar \{[^}]*margin-top/);
        for (const s of [...siniflar].filter(
            (s) => s !== "ekran" && s !== "bloklar",
        ))
            expect(css).toMatch(new RegExp(`\\.huni [^{,]*\\.${s}\\s*[{,]`));
        // Kutudaki simge seçenek simgesinin kuralına yaslanmaz.
        expect(css).toMatch(/\.huni \.kutu \.simge\s*\{/);
    });
});

describe("T1c — ekran kapağı", () => {
    const ciz = (duzen?: "ust" | "kahraman" | "tamEkran"): string =>
        renderToStaticMarkup(
            <Adim
                adim={{
                    tur: "ekran",
                    baslik: "Merhaba",
                    gorsel: G,
                    gorselMetni: "Kapak",
                    ...(duzen ? { duzen } : {}),
                    bloklar: [{ tur: "metin", metin: "Kısa" }],
                    dugme: "Başla",
                }}
                medyaKoku={KOK}
            />,
        ).replace(/<link[^>]*\/>/g, "");
    it("kapak başlığın üstünde, tanıtımın kapak sınıfıyla; ust'ta işaret yok", () => {
        expect(ciz()).toMatch(
            /^<section class="ekran"><img class="kapak" src="[^"]+" alt="Kapak" decoding="async"\/><h1 tabindex="-1">Merhaba<\/h1>/,
        );
        expect(ciz("ust")).not.toContain("data-duzen");
    });
    it("kahraman ve tamEkran bölümde işaret (tanıtımın CSS'i)", () => {
        expect(ciz("kahraman")).toContain(
            '<section class="ekran" data-duzen="kahraman">',
        );
        expect(ciz("tamEkran")).toContain(
            '<section class="ekran" data-duzen="tamEkran">',
        );
    });
    it("CSS: tam ekranda blok metinleri de beyaz", () => {
        expect(css).toMatch(
            /section\[data-duzen="tamEkran"\] \.bloklar[^{]*\{[^}]*color: rgb\(255 255 255/,
        );
    });
});

describe("T1b — ekranda soru bloğu", () => {
    const ekran = (
        secimTuru: "tekli" | "coklu" | "metin",
        ek: Partial<{ secili: number[]; cevapMetni: string }> = {},
    ): string =>
        renderToStaticMarkup(
            <Adim
                adim={{
                    tur: "ekran",
                    baslik: "Kiminle?",
                    bloklar: [
                        { tur: "metin", metin: "Bir tane seç." },
                        secimTuru === "metin"
                            ? { tur: "soru", secimTuru, yanitZorunlu: true }
                            : {
                                  tur: "soru",
                                  secimTuru,
                                  duzen: "izgara",
                                  secenekler: [
                                      { metin: "Ailemle", simge: "👪" },
                                      { metin: "İş" },
                                  ],
                              },
                    ],
                    ...(secimTuru === "tekli" ? {} : { dugme: "Gönder" }),
                }}
                medyaKoku={KOK}
                {...ek}
            />,
        );
    it("tekli: seçenekler blokların arasında, soru adımıyla aynı öğe; düğme yok", () => {
        const html = ekran("tekli");
        expect(html).toContain(
            '<p>Bir tane seç.</p><div class="secenekler" data-tur="tekli" data-duzen="izgara"><button type="button">',
        );
        expect(html).toContain(
            '<span class="simge" aria-hidden="true">👪</span><span>Ailemle</span>',
        );
        expect(html).not.toContain('class="eylem"');
    });
    it("çoklu: seçim yokken düğme kapalı, seçilince açık ve seçili işaretli", () => {
        expect(ekran("coklu")).toContain(
            '<button type="button" class="ana" disabled="">Gönder</button>',
        );
        const secili = ekran("coklu", { secili: [1] });
        expect(secili).toContain('class="ana">Gönder</button>');
        expect(secili).not.toContain("disabled");
        expect(secili).toContain('aria-pressed="true"><span>İş</span>');
    });
    it("serbest yanıt zorunluysa boşken kapalı; yazınca açık", () => {
        expect(ekran("metin")).toContain('required=""');
        expect(ekran("metin")).toContain('disabled="">Gönder');
        expect(ekran("metin", { cevapMetni: "Merhaba" })).not.toContain(
            "disabled",
        );
    });
});

describe("Mobil görünüm 3 — seçenek simgesi", () => {
    const html = renderToStaticMarkup(
        <Soru
            soru="Kiminle?"
            secenekler={[{ metin: "İş", simge: "💼" }, { metin: "Diğer" }]}
            medyaKoku={KOK}
        />,
    );
    it("simge metnin önünde, ekran okuyucuya gizli; simgesizde öğe yok", () => {
        expect(html).toContain(
            '<span class="simge" aria-hidden="true">💼</span><span>İş</span>',
        );
        expect(html.match(/class="simge"/g)).toHaveLength(1);
    });
    it("CSS: simge büyük, en az genişlikte ve esnemez", () => {
        expect(css).toMatch(/\.huni \.secenekler \.simge \{[^}]*font-size/);
        // Metin esner, simge esnemez (esneyen simge metni sağa iterdi).
        expect(css).toContain(
            ".huni .secenekler button > span:not(.isaret):not(.simge) {",
        );
        // Sabit genişlik bölünen emojide metnin üstüne taşıyordu (ölçüldü).
        expect(css).toMatch(
            /\.huni \.secenekler \.simge \{[^}]*min-width: 28px/,
        );
        expect(css).not.toMatch(
            /\.huni \.secenekler \.simge \{[^}]*[^-]width: 28px/,
        );
    });
});

describe("G5 — tanıtım düzeni", () => {
    const ciz = (duzen?: "ust" | "kahraman" | "tamEkran"): string =>
        renderToStaticMarkup(
            <Tanitim
                baslik="Merhaba"
                metin="Kısa"
                dugme="Başla"
                gorsel={G}
                duzen={duzen}
                medyaKoku={KOK}
            />,
        );
    // Bağımsız beklenti (§3): aynı bileşenin iki çıktısını kıyaslamak
    // ikisi birden "ust" yazsa da eşit kalırdı (K4 ilk denemede tutmadı).
    it("ust ya da yoksa işaret hiç yok (DOM eskisiyle aynı)", () => {
        expect(ciz()).not.toContain("data-duzen");
        expect(ciz("ust")).not.toContain("data-duzen");
        expect(ciz()).toContain("<section><img");
    });
    it("oynatıcının Adim'i düzeni tanımdan aktarır", () => {
        const html = renderToStaticMarkup(
            <Adim
                adim={{
                    tur: "tanitim",
                    baslik: "Merhaba",
                    metin: "Kısa",
                    dugme: "Başla",
                    gorsel: G,
                    duzen: "tamEkran",
                }}
                medyaKoku={KOK}
            />,
        );
        expect(html).toContain('<section data-duzen="tamEkran">');
    });
    it("kahraman ve tamEkran bölümde işaret olarak", () => {
        expect(ciz("kahraman")).toMatch(/<section data-duzen="kahraman">/);
        expect(ciz("tamEkran")).toMatch(/<section data-duzen="tamEkran">/);
    });
    it("CSS: kahraman görseli kırpar; tamEkran metni beyaz, karartma sabit", () => {
        expect(css).toMatch(
            /section\[data-duzen="kahraman"\] img\.kapak \{[^}]*object-fit: cover/,
        );
        expect(css).toMatch(
            /section\[data-duzen="tamEkran"\] \{[^}]*color: #ffffff/,
        );
        expect(css).toMatch(
            /section\[data-duzen="tamEkran"\]::before \{[^}]*linear-gradient/,
        );
        // Ölçülen kusur: düğmenin auto boşluğu başlığı karartmanın zayıf
        // yerinde bırakıyordu (beyaz görselde 2,58:1) — gorunum-g5.md.
        expect(css).toMatch(
            /section\[data-duzen="tamEkran"\] \.eylem \{[^}]*margin-top: 0/,
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
            /<div class="secenekler" data-tur="tekli"><button type="button">.*<span>Ailemle<\/span><span class="isaret" aria-hidden="true"><\/span>/,
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
        // Geri yalnız simge: yükseklik VE genişlik ≥ 44 px.
        const geri = blok("\\.huni button\\.geri");
        for (const boyut of ["height", "width"]) {
            expect(
                Number(
                    geri.match(
                        new RegExp(`(?:^|\\s)${boyut}:\\s*(\\d+)px`),
                    )?.[1],
                ),
            ).toBeGreaterThanOrEqual(44);
        }
    });
    it("yapışık ana düğme yalnız huni oynatıcısında (.adimlar), panelde değil", () => {
        // Puck önizlemesinde adımlar alt alta: yapışık düğme her adımı kaplardı.
        const yapisik = [
            ...css.matchAll(/([^{}]+)\{[^}]*position:\s*sticky[^}]*\}/g),
        ].map((m) => (m[1] ?? "").replace(/\/\*[\s\S]*?\*\//g, "").trim());
        expect(yapisik).toEqual([".huni .adimlar .eylem"]);
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
            "eylem",
            "faydalar",
            "fiyat",
            "isaret",
            "kapak",
            "logo",
            "plan-karti",
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
