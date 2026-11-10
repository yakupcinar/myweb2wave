// @vitest-environment happy-dom
// Rapor ekranı (B5): eski Twig sayfasının ölçtüğü gösterim kararları —
// özet = reklam + organik, yüzde biçimi, huni şeridi, menü işareti, zararlı
// metin yazı olarak. Beklenenler elle hesaplandı (tuzak §3).
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import type { components } from "../../sozlesme";
import { yollar } from "../main";

// P4: grafikler tembel parça — testte önceden yüklenir; ölçülen içerik,
// parçanın indirilme süresi değil (yavaş koşuda 1 sn'lik bekleme aşıldı).
await import("./GunlukGrafikler");

type PanelRaporu = components["schemas"]["PanelRaporu"];

const RAPOR: PanelRaporu = {
    gun: 30,
    gunler: [7, 30, 90],
    sayimlar: {
        reklam: { ziyaret: 3, odemeye_gecti: 3, deneme: 1, odedi: 1 },
        organik: { ziyaret: 1, odemeye_gecti: 0, deneme: 0, odedi: 0 },
    },
    izinler: { kabul: 2, red: 1, secmeyen: 1 },
    adimlar: [
        { app: "keepchat", huni: "—", sira: 0, adim: "karsilama", ulasan: 3 },
        { app: "keepchat", huni: "—", sira: 1, adim: "kiminle", ulasan: 2 },
        { app: "keepchat", huni: "—", sira: 2, adim: "cihaz", ulasan: 1 },
    ],
    cevaplar: [
        {
            app: "keepchat",
            soru: "kiminle",
            secenek: "aile",
            sayi: 4,
            abone: 1,
        },
    ],
    kampanyalar: [
        {
            kampanya: "ekim",
            harcama: { paraBirimi: "EUR", tutar: 15 },
            ziyaret: 3,
            odedi: 2,
            gelir: [
                { paraBirimi: "EUR", tutar: 9.99 },
                { paraBirimi: "TRY", tutar: 20 },
            ],
            roas: 0.67,
        },
        {
            kampanya: "sifir",
            harcama: { paraBirimi: "EUR", tutar: 0 },
            ziyaret: 0,
            odedi: 0,
            gelir: [],
            roas: null,
        },
    ],
    kohort: [
        {
            hafta: "2026-11-02",
            ziyaret: 2,
            pencereler: [
                {
                    gun: 0,
                    olgun: true,
                    kisiBasi: [{ paraBirimi: "EUR", tutar: 3.33 }],
                },
                { gun: 7, olgun: true, kisiBasi: [] },
                { gun: 30, olgun: false, kisiBasi: [] },
                { gun: 60, olgun: false, kisiBasi: [] },
            ],
        },
    ],
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

// P7a2: büyüyen üç liste kendi uçlarından, sayfalı.
const LISTELER: Record<string, unknown[]> = {
    ziyaretler: [
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
    kaynaklar: [
        {
            kaynak: "meta",
            ortam: "cpc",
            kampanya: "ekim",
            reklam: true,
            ziyaret: 3,
            odemeye_gecti: 3,
            deneme: 1,
            odedi: 1,
            gelir: [
                { paraBirimi: "EUR", tutar: 29.97 },
                { paraBirimi: "TRY", tutar: 20 },
            ],
        },
        {
            kaynak: "doğrudan",
            ortam: null,
            kampanya: null,
            reklam: false,
            ziyaret: 1,
            odemeye_gecti: 0,
            deneme: 0,
            odedi: 0,
            gelir: [],
        },
    ],
    serbestYanitlar: [],
};

let istenen: string[] = [];
let toplamlar: Record<string, number> = {};

function kur(adres = "/panel", veri: PanelRaporu = RAPOR): void {
    istenen = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            istenen.push(istek.url);
            const adres = new URL(istek.url, "http://x");
            const liste = adres.pathname.split("/panel/api/rapor/")[1];
            if (liste) {
                const satirlar = LISTELER[liste] ?? [];
                return new Response(
                    JSON.stringify({
                        sayfa: Number(adres.searchParams.get("sayfa") ?? 1),
                        boyut: 25,
                        toplam: toplamlar[liste] ?? satirlar.length,
                        satirlar,
                    }),
                    { headers: { "Content-Type": "application/json" } },
                );
            }
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
    toplamlar = {};
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
        // 3+1, 3+0, 1+0, 1+0; Ç118: dönüşüm = (deneme + ödedi) / ziyaret —
        // yalnız ödedi'den 1/4 = 25 % olurdu, 2/4 = 50 %.
        expect(
            [...ozet.querySelectorAll("strong")].map((s) => s.textContent),
        ).toEqual(["4", "3", "1", "1", "50,0 %"]);
        expect(dd("Reklamdan gelen")).toEqual(["3", "3", "1", "1", "66,7 %"]);
        expect(dd("Organik / doğrudan")).toEqual(["1", "0", "0", "0", "0,0 %"]);
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
            odeme.getByText(
                "3 gün (UTC) · toplam başlatılan ödeme 3, tamamlanan ödeme (deneme dahil) 1",
            ),
        ).toBeTruthy();
        expect(ziyaret.queryByRole("list", { name: "Gösterge" })).toBeNull();
        expect(
            within(odeme.getByRole("list", { name: "Gösterge" }))
                .getAllByRole("listitem")
                .map((l) => l.textContent),
        ).toEqual(["Başlatılan ödeme", "Tamamlanan ödeme (deneme dahil)"]);
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

    it("M5c: kaynak kırılımı, cevaba göre dönüşüm, kohort hücreleri", async () => {
        kur();
        const hucreler = (bolum: string, satir = 1) =>
            [
                ...within(screen.getByRole("region", { name: bolum }))
                    .getAllByRole("row")
                    [satir]!.querySelectorAll("td"),
            ].map((h) => h.textContent);
        await screen.findByRole("region", { name: "Kaynak kırılımı" });
        // Dönüşüm (deneme + ödedi) / ziyaret = 2/3; para birimleri toplanmaz.
        expect(hucreler("Kaynak kırılımı")).toEqual([
            "reklam · meta",
            "cpc",
            "ekim",
            "3",
            "3",
            "1",
            "1",
            "66,7 %",
            "€29,97 · ₺20,00",
        ]);
        expect(hucreler("Kaynak kırılımı", 2)).toEqual([
            "doğrudan",
            "—",
            "—",
            "1",
            "0",
            "0",
            "0",
            "0,0 %",
            "—",
        ]);
        // 1 / 4 seçen abone.
        expect(hucreler("Cevaplar")).toEqual([
            "kiminle",
            "aile",
            "4",
            "1",
            "25,0 %",
        ]);
        // M5d: ROAS hesabın para biriminde; harcama 0 → tanımsız "—".
        expect(hucreler("Meta kampanyaları")).toEqual([
            "ekim",
            "€15,00",
            "3",
            "2",
            "€9,99 · ₺20,00",
            "0,67×",
        ]);
        expect(hucreler("Meta kampanyaları", 2).at(-1)).toBe("—");
        // Olgun + gelirsiz "0", olgun değil "—" (yarım pencere sıfır sanılmasın).
        expect(hucreler("Kohort geliri")).toEqual([
            "2026-11-02",
            "2",
            "€3,33",
            "0",
            "—",
            "—",
        ]);
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

    it("gün aralığı adresten okunur ve rapor + liste uçlarına gider; tanınmayan 30", async () => {
        const gunler = (): string[] => [
            ...new Set(
                istenen.map(
                    (u) => new URL(u, "http://x").searchParams.get("gun") ?? "",
                ),
            ),
        ];
        const yollar = (): string[] =>
            istenen.map((u) => new URL(u, "http://x").pathname).sort();
        // Sunucu seçilen aralığı yanıtta geri verir; listeler onu kullanır.
        kur("/panel?gun=90", { ...RAPOR, gun: 90 });
        await screen.findByRole("region", { name: "Ziyaretler" });
        await screen.findByRole("region", { name: "Kaynak kırılımı" });
        await screen.findByRole("region", { name: "Serbest yanıtlar" });
        expect(yollar()).toEqual([
            "/panel/api/rapor",
            "/panel/api/rapor/kaynaklar",
            "/panel/api/rapor/serbestYanitlar",
            "/panel/api/rapor/ziyaretler",
        ]);
        expect(gunler()).toEqual(["90"]);
        expect(
            screen
                .getByRole("link", { name: "Son 90 gün" })
                .getAttribute("aria-current"),
        ).toBe("page");
        cleanup();
        kur("/panel?gun=5");
        await screen.findByRole("region", { name: "Ziyaretler" });
        expect(gunler()).toEqual(["30"]);
    });

    it("P7a2: liste sayfalı — sayı, sonraki sayfa isteği, sınırda düğme kapalı", async () => {
        toplamlar = { ziyaretler: 30 };
        kur();
        const ziyaret = await screen.findByRole("region", {
            name: "Ziyaretler",
        });
        const sayfalama = within(ziyaret.parentElement!).getByRole(
            "navigation",
            {
                name: "Sayfalama",
            },
        );
        expect(sayfalama.textContent).toContain("1–25 / 30 ziyaret");
        const onceki = within(sayfalama).getByRole("button", {
            name: "‹ Önceki",
        });
        expect((onceki as HTMLButtonElement).disabled).toBe(true);
        fireEvent.click(
            within(sayfalama).getByRole("button", { name: "Sonraki ›" }),
        );
        await waitFor(() =>
            expect(
                istenen
                    .map((u) => new URL(u, "http://x"))
                    .find(
                        (u) =>
                            u.pathname === "/panel/api/rapor/ziyaretler" &&
                            u.searchParams.get("sayfa") === "2",
                    )
                    ?.searchParams.get("gun"),
            ).toBe("30"),
        );
        await waitFor(() =>
            expect(sayfalama.textContent).toContain("26–30 / 30 ziyaret"),
        );
        expect(
            (
                within(sayfalama).getByRole("button", {
                    name: "Sonraki ›",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        // Kaynaklar tek sayfaya sığar: yalnız sayı, düğme yok.
        const kaynak = await screen.findByRole("region", {
            name: "Kaynak kırılımı",
        });
        const k = within(kaynak.parentElement!).getByRole("navigation", {
            name: "Sayfalama",
        });
        expect([
            k.textContent,
            within(k).queryAllByRole("button").length,
        ]).toEqual(["2 kaynak", 0]);
    });
});
