// @vitest-environment happy-dom
// Rapor ekranı (B5): eski Twig sayfasının ölçtüğü gösterim kararları —
// özet = reklam + organik, yüzde biçimi, huni şeridi, menü işareti, zararlı
// metin yazı olarak. Beklenenler elle hesaplandı (tuzak §3).
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
    cleanup,
    render,
    screen,
    waitFor,
    within,
} from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import type { components } from "../../sozlesme";
import { yollar } from "../main";

type PanelRaporu = components["schemas"]["PanelRaporu"];

const RAPOR: PanelRaporu = {
    gun: 30,
    gunler: [7, 30, 90],
    azami: 200,
    sayimlar: {
        reklam: { ziyaret: 2, odemeye_gecti: 2, odedi: 1 },
        organik: { ziyaret: 1, odemeye_gecti: 0, odedi: 0 },
    },
    izinler: { kabul: 2, red: 1, secmeyen: 1 },
    satirlar: [
        {
            zaman: "2026-10-27T06:31:58+00:00",
            app: "keepchat",
            huni: null,
            kaynak: "<script>alert(1)</script>",
            kampanya: "ekim-1",
            reklam: true,
            asama: "ödedi",
            durum: "active",
            email: "a***@example.com",
        },
    ],
    adimlar: [
        { app: "keepchat", huni: "—", sira: 0, adim: "karsilama", ulasan: 3 },
        { app: "keepchat", huni: "—", sira: 1, adim: "kiminle", ulasan: 2 },
        { app: "keepchat", huni: "—", sira: 2, adim: "cihaz", ulasan: 1 },
    ],
    cevaplar: [{ app: "keepchat", soru: "kiminle", secenek: "aile", sayi: 2 }],
    metinCevaplari: [],
    gunluk: [
        {
            gun: "2026-11-01",
            ziyaret: 3,
            odemeBaslatildi: 1,
            odemeTamamlandi: 0,
        },
        {
            gun: "2026-11-02",
            ziyaret: 0,
            odemeBaslatildi: 0,
            odemeTamamlandi: 0,
        },
        {
            gun: "2026-11-03",
            ziyaret: 5,
            odemeBaslatildi: 2,
            odemeTamamlandi: 1,
        },
    ],
};

let istenen: string[] = [];

function kur(adres = "/panel", veri: PanelRaporu = RAPOR): void {
    istenen = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            istenen.push(istek.url);
            return new Response(JSON.stringify(veri), {
                headers: { "Content-Type": "application/json" },
            });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, { initialEntries: [adres] })}
            />
        </QueryClientProvider>,
    );
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

const dd = (bolum: string): string[] =>
    within(screen.getByRole("region", { name: bolum }))
        .getAllByRole("definition")
        .map((d) => d.textContent ?? "");

describe("Rapor", () => {
    it("özet reklam + organik; kaynak kartları ve izin oranı", async () => {
        kur();
        const ozet = await screen.findByRole("region", { name: "Özet" });
        // 2+1, 2+0, 1+0; 1/3 = 33,3 %
        expect(
            [...ozet.querySelectorAll("strong")].map((s) => s.textContent),
        ).toEqual(["3", "2", "1", "33,3 %"]);
        expect(dd("Reklamdan gelen")).toEqual(["2", "2", "1", "50,0 %"]);
        expect(dd("Organik / doğrudan")).toEqual(["1", "0", "0", "0,0 %"]);
        expect(dd("Reklam ölçümü izni")).toEqual([
            "2 · 50,0 %",
            "1 · 25,0 %",
            "1 · 25,0 %",
        ]);
    });

    it("P4: iki ayrı grafik, toplamlar, gösterge yalnız iki seride, tablo görünümü UTC günüyle", async () => {
        kur();
        const ziyaret = within(
            await screen.findByRole("region", { name: "Günlük ziyaret" }),
        );
        const odeme = within(
            screen.getByRole("region", { name: "Günlük ödeme" }),
        );
        expect(
            ziyaret.getByText("3 gün (UTC) · toplam ziyaret 8"),
        ).toBeTruthy();
        expect(
            odeme.getByText("3 gün (UTC) · toplam ödemeye geçti 3, ödedi 1"),
        ).toBeTruthy();
        expect(ziyaret.queryByRole("list", { name: "Gösterge" })).toBeNull();
        expect(
            within(odeme.getByRole("list", { name: "Gösterge" }))
                .getAllByRole("listitem")
                .map((l) => l.textContent),
        ).toEqual(["Ödemeye geçti", "Ödedi"]);
        const satir = (k: ReturnType<typeof within>, gun: string) =>
            [
                ...k
                    .getByText("Tablo olarak göster")
                    .closest("details")!
                    .querySelectorAll(`tr[data-gun="${gun}"] td`),
            ].map((td) => td.textContent);
        expect(satir(odeme, "2026-11-03")).toEqual(["3 Kasım Sal", "2", "1"]);
        expect(satir(ziyaret, "2026-11-02")).toEqual(["2 Kasım Pzt", "0"]);
    });

    it("P4: kayıtsız aralıkta grafik yerine boş durum", async () => {
        kur("/panel", {
            ...RAPOR,
            gunluk: RAPOR.gunluk.map((g) => ({
                ...g,
                ziyaret: 0,
                odemeBaslatildi: 0,
                odemeTamamlandi: 0,
            })),
        });
        const odeme = within(
            await screen.findByRole("region", { name: "Günlük ödeme" }),
        );
        expect(odeme.getByText("Bu aralıkta kayıt yok.")).toBeTruthy();
    });

    it("anket adımı oranı ve şerit genişliği ilk adıma göre", async () => {
        kur();
        const tablo = await screen.findByRole("region", {
            name: "Anket adımları",
        });
        const satirlar = within(tablo).getAllByRole("row").slice(1);
        expect(satirlar.map((s) => s.lastElementChild?.textContent)).toEqual([
            "100 %",
            "67 %",
            "33 %",
        ]);
        expect(
            [...tablo.querySelectorAll<HTMLElement>("[data-serit] > span")].map(
                (s) => s.style.width,
            ),
        ).toEqual(["100.0%", "66.7%", "33.3%"]);
    });

    it("zararlı kaynak metni yazı olarak; e-posta maskeli; menüde Rapor işaretli", async () => {
        kur();
        const ziyaret = await screen.findByRole("region", {
            name: "Ziyaretler",
        });
        const hucreler = within(ziyaret)
            .getAllByRole("row")[1]!
            .querySelectorAll("td");
        expect([...hucreler].slice(1).map((h) => h.textContent)).toEqual([
            "keepchat",
            "—",
            "reklam · <script>alert(1)</script>",
            "ekim-1",
            "ödedi",
            "active",
            "a***@example.com",
        ]);
        expect(document.querySelector("script")).toBeNull();
        const menu = screen.getByRole("navigation", { name: "Panel" });
        expect(
            [...menu.querySelectorAll("[aria-current=page]")].map((a) =>
                a.textContent?.trim(),
            ),
        ).toEqual(["Rapor"]);
    });

    it("gün aralığı adresten okunur ve uca gider; tanınmayan 30", async () => {
        kur("/panel?gun=90");
        await screen.findByRole("region", { name: "Özet" });
        expect(
            new URL(istenen[0] ?? "", "http://x").searchParams.get("gun"),
        ).toBe("90");
        expect(
            screen
                .getByRole("link", { name: "Son 90 gün" })
                .getAttribute("aria-current"),
        ).toBe("page");
        cleanup();
        kur("/panel?gun=5");
        await waitFor(() => expect(istenen).toHaveLength(1));
        expect(
            new URL(istenen[0] ?? "", "http://x").searchParams.get("gun"),
        ).toBe("30");
    });
});
