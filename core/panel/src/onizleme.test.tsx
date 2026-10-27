// Önizleme = huni (B4/H3): aynı tanımdan Puck'ın çizdiği adım ile huni
// oynatıcısının çizdiği adım aynı DOM. İki ayrı yol karşılaştırılır — Puck
// alanları → bileşen (yapilandirma.tsx) ve tanım → bileşen (Adim) — biri
// diğerinden hesaplanmaz (tuzak §3).
import type { ReactNode } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { Adim } from "../../../huni-bilesenleri/bilesenler";
import { temaDegiskenleri } from "../../../huni-bilesenleri/tema";
import { tanimdanPuck, type Tanim, type Tema } from "./donustur";
import { yapilandirma } from "./yapilandirma";

const VARSAYILAN: Tema = {
    anaRenk: "#2563eb",
    arkaPlan: "#ffffff",
    metinRengi: "#111827",
    yaziTipi: "sistem",
    kose: 10,
};
const G1 = "a".repeat(64);
const G2 = "b".repeat(64);

const tanim: Tanim = {
    sayfaBasligi: "KeepChat Pro",
    tema: {
        anaRenk: "#16a34a",
        arkaPlan: "#0b1220",
        metinRengi: "#f8fafc",
        yaziTipi: "yuvarlak",
        kose: 20,
    },
    adimlar: [
        {
            id: "karsilama",
            tur: "tanitim",
            baslik: "Merhaba <b>",
            metin: "Kısa",
            dugme: "Başla",
            gorsel: G1,
            gorselMetni: "Sohbet ekranı", // Ç44: iki yoldan aynı alt
        },
        {
            id: "yalin",
            tur: "tanitim",
            baslik: "Görselsiz",
            metin: "",
            dugme: "Devam",
        },
        {
            id: "kiminle",
            tur: "soru",
            soru: "Kiminle?",
            aciklama: "Birden fazla seçenek seçebilirsin.",
            secenekler: [
                {
                    id: "aile",
                    metin: "Ailemle",
                    gorsel: G2,
                    gorselMetni: "Aile",
                },
                { id: "is", metin: "İş" },
            ],
        },
        {
            id: "yorum",
            tur: "yorum",
            baslik: "Deneyim",
            metin: "Çok faydalı",
            yazar: "Ayşe",
            unvan: "Kullanıcı",
            dugme: "Devam",
        },
        {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "9,99 €",
            dugme: "Abone ol",
            plan: "monthly",
            // Ç61 (3): fayda + yasal bağlantı da iki yoldan aynı DOM.
            faydalar: ["Sınırsız sohbet", "Reklamsız"],
            sartlar: "https://ornek.com/sartlar",
            iptal: "https://ornek.com/iptal",
        },
    ],
};

const yap = yapilandirma({
    planlar: ["monthly"],
    yaziTipleri: ["sistem", "serif", "yuvarlak", "mono"],
    medyaAdresi: "",
    jeton: "",
});
const veri = tanimdanPuck(tanim, VARSAYILAN);
// React 19'un sunucu çizimi görseller için başa <link rel="preload"> koyuyor
// (tarayıcıdaki createRoot koymaz) — iki tarafta da ayıklanır.
const html = (n: ReactNode): string =>
    renderToStaticMarkup(n).replace(/<link rel="preload"[^>]*\/>/g, "");

/** Puck'ın render'ı, Puck'ın verdiği gibi (kendi alanlarıyla) çağrılır. */
function puckCiz(i: number): string {
    const oge = veri.content[i];
    if (!oge) throw new Error(`adım ${i} yok`);
    const render = yap.components[oge.type].render as (p: unknown) => ReactNode;
    return html(render({ ...oge.props, puck: { isEditing: true } }));
}

/** Düzenleyiciye özgü ayırıcı kutu: adımlar alt alta görünsün diye. */
const ayiriciyiAt = (s: string): string =>
    s.replace(/^<div style="[^"]*">([\s\S]*)<\/div>$/, "$1");

describe("önizleme = huni: adım DOM'u", () => {
    it.each(tanim.adimlar.map((a, i) => [a.id, i] as const))("%s", (_id, i) => {
        const puck = puckCiz(i);
        expect(puck).toMatch(/^<div style="/); // ayırıcı gerçekten orada
        expect(ayiriciyiAt(puck)).toBe(
            html(<Adim adim={tanim.adimlar[i]!} medyaKoku="" />),
        );
    });
});

describe("önizleme = huni: tema", () => {
    // Huni sayfası tema varken temaDegiskenleri'ni köke yazar (Huni.tsx);
    // Puck kökü aynı değişkenleri dış kutuya yazmalı.
    it("özel tema → aynı CSS değişkenleri, sütun .huni", () => {
        const kok = yap.root?.render as (p: unknown) => ReactNode;
        // Yazı tipi yığınındaki tek tırnak öznitelikte kaçışlanır.
        const cikti = html(
            kok({ ...veri.root.props, children: "ICERIK", puck: {} }),
        ).replaceAll("&#x27;", "'");
        for (const [ad, deger] of Object.entries(
            temaDegiskenleri(tanim.tema!),
        )) {
            expect(cikti).toContain(`${ad}:${deger}`);
        }
        expect(cikti).toContain('<div class="huni">ICERIK</div>');
    });
    // G3: zemin de aynı değişkenle; dış kutu onu çizer (huni sayfasında body.huni).
    it("zemin geçişi → --zemin değişkeni ve dış kutu onu çizer", () => {
        const kok = yap.root?.render as (p: unknown) => ReactNode;
        const zeminli = tanimdanPuck(
            {
                ...tanim,
                tema: {
                    ...tanim.tema!,
                    zemin: { tur: "gecis", ikinciRenk: "#1e3a8a" },
                },
            },
            VARSAYILAN,
        );
        const cikti = html(
            kok({ ...zeminli.root.props, children: "ICERIK", puck: {} }),
        );
        expect(cikti).toContain(
            "--zemin:linear-gradient(180deg, #0b1220, #1e3a8a)",
        );
        expect(cikti).toContain("background:var(--zemin, var(--arka))");
    });
});
