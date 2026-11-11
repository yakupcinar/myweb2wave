// @vitest-environment happy-dom
// Rapor ekranı (B5): eski Twig sayfasının ölçtüğü gösterim kararları —
// özet = reklam + organik, yüzde biçimi, huni şeridi, menü işareti, zararlı
// metin yazı olarak. Beklenenler elle hesaplandı (tuzak §3). P7i: bölümler
// sekmelerde — her test ilgili sekmeyi adresle açar.
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
    gelir: [{ paraBirimi: "EUR", tutar: 29.97 }],
    onceki: {
        sayimlar: {
            reklam: { ziyaret: 2, odemeye_gecti: 2, deneme: 0, odedi: 1 },
            organik: { ziyaret: 1, odemeye_gecti: 0, deneme: 0, odedi: 0 },
        },
        gelir: [{ paraBirimi: "EUR", tutar: 20 }],
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
            huni: "—",
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

const SECENEKLER = {
    huniler: ["genel", "fitness"],
    kaynaklar: ["meta", "doğrudan"],
    kampanyalar: ["ekim"],
    ulkeler: ["TR", "DE"],
    deneyler: [{ deney: "d1", varyantlar: ["a", "b"] }],
};

function kur(adres = "/panel", veri: PanelRaporu = RAPOR): void {
    istenen = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            istenen.push(istek.url);
            const adres = new URL(istek.url, "http://x");
            const liste = adres.pathname.split("/panel/api/rapor/")[1];
            // R1b: süzgeç seçenekleri.
            if (liste === "secenekler")
                return new Response(JSON.stringify(SECENEKLER), {
                    headers: { "Content-Type": "application/json" },
                });
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

const kartlar = (bolum: string): string[][] =>
    [
        ...screen
            .getByRole("region", { name: bolum })
            .querySelectorAll<HTMLElement>("section[aria-label]"),
    ].map((k) =>
        [...k.querySelectorAll("span")].map((s) => s.textContent ?? ""),
    );

const hucreler = (tablo: string, satir = 1): (string | null)[] =>
    [
        ...within(screen.getByRole("table", { name: tablo }))
            .getAllByRole("row")
            [satir]!.querySelectorAll("td"),
    ].map((h) => h.textContent);

describe("Rapor", () => {
    it("P7b: Özet önceki döneme göre — gelir, yeni abone, oran (puan), ziyaretçi başına gelir", async () => {
        kur();
        await screen.findByRole("region", { name: "Özet" });
        // Şimdi: ziyaret 3+1, abone (deneme+ödedi) 2+0, gelir €29,97.
        // Önce: ziyaret 2+1, abone 1, gelir €20. Hesaplar elle (§3):
        // gelir (29,97−20)/20 = %49,85 → %50; abone 1 → 2 = %100;
        // oran 2/4 = %50,0, önce 1/3 = %33,3 → 16,7 puan;
        // kişi başı 29,97/4 = 7,49 (önce 6,67) → %12.
        expect(kartlar("Özet")).toEqual([
            ["Gelir", "€29,97", "↑ %50"],
            ["Yeni abone", "2", "↑ %100"],
            ["Ziyaretten aboneliğe", "%50,0", "↑ 16,7 puan"],
            ["Ziyaretçi başına gelir", "€7,49", "↑ %12"],
        ]);
        expect(screen.getByText("Önceki 30 güne göre.")).toBeTruthy();
    });

    it("P7b: önceki dönem boşsa ve para birimleri farklıysa karşılaştırma söylenmez", async () => {
        kur("/panel", {
            ...RAPOR,
            gelir: [
                { paraBirimi: "EUR", tutar: 10 },
                { paraBirimi: "TRY", tutar: 20 },
            ],
            onceki: {
                sayimlar: {
                    reklam: {
                        ziyaret: 0,
                        odemeye_gecti: 0,
                        deneme: 0,
                        odedi: 0,
                    },
                    organik: {
                        ziyaret: 0,
                        odemeye_gecti: 0,
                        deneme: 0,
                        odedi: 0,
                    },
                },
                gelir: [],
            },
        });
        await screen.findByRole("region", { name: "Özet" });
        expect(kartlar("Özet").map((k) => k[2])).toEqual([
            "Karşılaştırma yok",
            "Önceki dönemde veri yok",
            "Önceki dönemde veri yok",
            "Karşılaştırma yok",
        ]);
    });

    it("P7b: iki dönemde farklı tek para birimi karşılaştırılmaz", async () => {
        kur("/panel", {
            ...RAPOR,
            onceki: {
                ...RAPOR.onceki,
                gelir: [{ paraBirimi: "TRY", tutar: 20 }],
            },
        });
        await screen.findByRole("region", { name: "Özet" });
        expect(kartlar("Özet").map((k) => k[2])).toEqual([
            "Karşılaştırma yok",
            "↑ %100",
            "↑ 16,7 puan",
            "Karşılaştırma yok",
        ]);
    });

    it("P7b: Ziyaretten aboneliğe tablosu reklam / organik / toplam; izin kartları", async () => {
        kur();
        await screen.findByRole("table", { name: "Ziyaretten aboneliğe" });
        expect(
            [1, 2, 3, 4, 5].map((i) => hucreler("Ziyaretten aboneliğe", i)),
        ).toEqual([
            ["Ziyaret", "3", "1", "4"],
            ["Ödeme sayfasını açtı", "3", "0", "3"],
            ["Ücretsiz denemeye başladı", "1", "0", "1"],
            ["Ödeme yaptı · deneme sonrası dahil", "1", "0", "1"],
            ["Abone oldu", "2", "0", "2"],
        ]);
        cleanup();
        kur("/panel?sekme=kaynaklar");
        await screen.findByRole("region", { name: "Reklam ölçümü izni" });
        expect(kartlar("Reklam ölçümü izni")).toEqual([
            ["Kabul etti", "2", "%50,0"],
            ["Reddetti", "1", "%25,0"],
            ["Seçmedi", "1", "%25,0"],
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

    it("M5c: kaynaklar, cevaba göre dönüşüm, Meta kampanyaları, kohort hücreleri", async () => {
        kur("/panel?sekme=kaynaklar");
        await screen.findByRole("table", { name: "Kaynaklar" });
        // Dönüşüm (deneme + ödedi) / ziyaret = 2/3; para birimleri toplanmaz.
        expect(hucreler("Kaynaklar")).toEqual([
            "meta/ cpcreklam",
            "ekim",
            "3",
            "1",
            "%66,7",
            "€29,97 · ₺20,00",
        ]);
        expect(hucreler("Kaynaklar", 2)).toEqual([
            "doğrudan",
            "—",
            "1",
            "0",
            "%0,0",
            "—",
        ]);
        cleanup();
        kur("/panel?sekme=anket");
        await screen.findByRole("table", { name: "Cevaplar" });
        // 1 / 4 seçen abone.
        expect(hucreler("Cevaplar")).toEqual([
            "kiminle",
            "aile",
            "4",
            "1",
            "%25,0",
        ]);
        cleanup();
        kur();
        await screen.findByRole("table", { name: "Meta kampanyaları" });
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
        cleanup();
        kur("/panel?sekme=gelir");
        await screen.findByRole("table", { name: "Kohort geliri" });
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

    it("P7b: Meta harcaması yoksa boş kart ve bağlama bağlantısı", async () => {
        kur("/panel", { ...RAPOR, kampanyalar: [] });
        const kart = await screen.findByRole("region", {
            name: "Meta harcaması yok",
        });
        expect(kart.textContent).toContain("Bu dönemde Meta harcaması yok");
        expect(
            within(kart)
                .getByRole("link", { name: "Meta hesabını bağla" })
                .getAttribute("href"),
        ).toBe("/panel/ayarlar");
        expect(
            screen.queryByRole("table", { name: "Meta kampanyaları" }),
        ).toBeNull();
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

    it("P7b: anket şeridi ilk adıma göre, düşüş bir önceki adıma göre", async () => {
        kur("/panel?sekme=anket");
        const anket = await screen.findByRole("region", { name: "Anket" });
        // 3 → 2 → 1: şerit 100 / 66,7 / 33,3; düşüş —, (3−2)/3 = %33, (2−1)/2 = %50.
        expect(
            [...anket.querySelectorAll<HTMLElement>("[data-serit] > span")].map(
                (s) => s.style.width,
            ),
        ).toEqual(["100.0%", "66.7%", "33.3%"]);
        expect(
            [...anket.querySelectorAll("[data-adim]")].map((a) =>
                [...a.children].map((c) => c.textContent),
            ),
        ).toEqual([
            ["karsilama", "", "3", "—"],
            ["kiminle", "", "2", "−%33"],
            ["cihaz", "", "1", "−%50"],
        ]);
    });

    it("zararlı kaynak metni yazı olarak; e-posta maskeli; menüde Rapor işaretli", async () => {
        kur("/panel?sekme=ziyaretler");
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
        kur("/panel?gun=90&sekme=ziyaretler", { ...RAPOR, gun: 90 });
        await screen.findByRole("region", { name: "Ziyaretler" });
        // P7i: yalnız açık sekmenin listesi istenir.
        expect(yollar()).toEqual([
            "/panel/api/rapor",
            "/panel/api/rapor/secenekler",
            "/panel/api/rapor/ziyaretler",
        ]);
        expect(gunler()).toEqual(["90"]);
        const gun = (ad: string): HTMLElement =>
            within(
                screen.getByRole("navigation", { name: "Zaman aralığı" }),
            ).getByRole("link", { name: ad });
        expect(gun("90 gün").getAttribute("aria-current")).toBe("page");
        // Gün seçicisi sekmeyi korur.
        expect(gun("7 gün").getAttribute("href")).toBe(
            "/panel?gun=7&sekme=ziyaretler",
        );
        expect(gun("30 gün").getAttribute("href")).toBe(
            "/panel?sekme=ziyaretler",
        );
        cleanup();
        kur("/panel?gun=90&sekme=anket", { ...RAPOR, gun: 90 });
        await screen.findByRole("region", { name: "Serbest yanıtlar" });
        expect(yollar()).toEqual([
            "/panel/api/rapor",
            "/panel/api/rapor/secenekler",
            "/panel/api/rapor/serbestYanitlar",
        ]);
        expect(gunler()).toEqual(["90"]);
        cleanup();
        kur("/panel?gun=5&sekme=kaynaklar");
        await screen.findByRole("region", { name: "Kaynaklar" });
        expect(gunler()).toEqual(["30"]);
    });

    it("P7a2: liste sayfalı — sayı, sonraki sayfa isteği, sınırda düğme kapalı", async () => {
        toplamlar = { ziyaretler: 30 };
        kur("/panel?sekme=ziyaretler");
        const ziyaret = await screen.findByRole("region", {
            name: "Ziyaretler",
        });
        const sayfalama = within(ziyaret).getByRole("navigation", {
            name: "Sayfalama",
        });
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
        cleanup();
        kur("/panel?sekme=kaynaklar");
        const kaynak = await screen.findByRole("region", {
            name: "Kaynaklar",
        });
        const k = within(kaynak).getByRole("navigation", {
            name: "Sayfalama",
        });
        expect([
            k.textContent,
            within(k).queryAllByRole("button").length,
        ]).toEqual(["2 kaynak", 0]);
    });

    it("P7i: sekmeler — varsayılan Genel bakış, günü korur, bölümler sekmesinde; tanınmayan sekme genel", async () => {
        const bolumler = (): string[] =>
            [...document.querySelectorAll("main section[aria-label]")]
                .filter((b) => !b.parentElement?.closest("section[aria-label]"))
                .map((b) => b.getAttribute("aria-label") ?? "");
        const sekme = (ad: string): HTMLElement =>
            within(
                screen.getByRole("navigation", { name: "Rapor bölümleri" }),
            ).getByRole("link", { name: ad });
        kur("/panel?gun=7");
        await screen.findByRole("region", { name: "Özet" });
        expect(bolumler()).toEqual([
            "Özet",
            "Günlük",
            "Ziyaretten aboneliğe",
            "Reklam harcaması",
        ]);
        expect(sekme("Genel bakış").getAttribute("aria-current")).toBe("page");
        expect(
            ["Genel bakış", "Anket", "Kaynaklar", "Gelir", "Ziyaretler"].map(
                (ad) => sekme(ad).getAttribute("href"),
            ),
        ).toEqual([
            "/panel?gun=7",
            "/panel?gun=7&sekme=anket",
            "/panel?gun=7&sekme=kaynaklar",
            "/panel?gun=7&sekme=gelir",
            "/panel?gun=7&sekme=ziyaretler",
        ]);
        // Genel bakışta liste uçları istenmez.
        expect(
            istenen.some(
                (u) =>
                    u.includes("/panel/api/rapor/") &&
                    !u.includes("/panel/api/rapor/secenekler"),
            ),
        ).toBe(false);

        fireEvent.click(sekme("Kaynaklar"));
        await screen.findByRole("region", { name: "Kaynaklar" });
        expect(bolumler()).toEqual(["Kaynaklar", "Reklam ölçümü izni"]);
        expect(sekme("Kaynaklar").getAttribute("aria-current")).toBe("page");
        fireEvent.click(sekme("Gelir"));
        await screen.findByRole("region", { name: "Kohort geliri" });
        expect(bolumler()).toEqual(["Kohort geliri"]);

        cleanup();
        kur("/panel?sekme=yok");
        await screen.findByRole("region", { name: "Özet" });
        expect(sekme("Genel bakış").getAttribute("aria-current")).toBe("page");
    });

    it("P7i: Anket tek huni — varsayılan en çok ziyaretli; seçim adrese; Cevaplar o huniden; tek hunide seçici yok", async () => {
        const adim = (
            huni: string,
            sira: number,
            adim: string,
            ulasan: number,
        ) => ({
            app: "keepchat",
            huni,
            sira,
            adim,
            ulasan,
        });
        const cevap = (huni: string, secenek: string) => ({
            app: "keepchat",
            huni,
            soru: "kiminle",
            secenek,
            sayi: 2,
            abone: 1,
        });
        kur("/panel?sekme=anket", {
            ...RAPOR,
            adimlar: [
                adim("fitness", 0, "giris", 3),
                adim("fitness", 1, "hedef", 1),
                adim("genel", 0, "karsilama", 5),
                adim("genel", 1, "kiminle", 4),
            ],
            cevaplar: [cevap("fitness", "spor"), cevap("genel", "aile")],
        });
        const secim = (await screen.findByRole("combobox", {
            name: "Anket hunisi",
        })) as HTMLSelectElement;
        expect([...secim.options].map((o) => o.textContent)).toEqual([
            "genel · 5 ziyaret",
            "fitness · 3 ziyaret",
        ]);
        const adimlar = (): string[] =>
            [...document.querySelectorAll("[data-adim]")].map(
                (a) => a.getAttribute("data-adim") ?? "",
            );
        expect(adimlar()).toEqual(["karsilama", "kiminle"]);
        expect(hucreler("Cevaplar")[1]).toBe("aile");
        expect(
            within(
                screen.getByRole("table", { name: "Cevaplar" }),
            ).getAllByRole("row").length,
        ).toBe(2);

        fireEvent.change(secim, { target: { value: "keepchat/fitness" } });
        await waitFor(() => expect(adimlar()).toEqual(["giris", "hedef"]));
        expect(hucreler("Cevaplar")[1]).toBe("spor");
        expect(
            within(screen.getByRole("navigation", { name: "Zaman aralığı" }))
                .getByRole("link", { name: "7 gün" })
                .getAttribute("href"),
        ).toBe("/panel?gun=7&sekme=anket&anket=keepchat%2Ffitness");

        cleanup();
        kur("/panel?sekme=anket");
        await screen.findByRole("region", { name: "Anket" });
        expect(
            screen.queryByRole("combobox", { name: "Anket hunisi" }),
        ).toBeNull();
    });

    it("R1b: süzgeç — seçenekler ve ülke adları; seçim rapora, listeye ve bağlantılara; deney varyantı sıfırlar; temizle", async () => {
        const son = (yol: string): URLSearchParams =>
            new URL(
                [...istenen]
                    .reverse()
                    .find((u) => new URL(u, "http://x").pathname === yol) ??
                    "http://x/",
                "http://x",
            ).searchParams;
        kur("/panel?gun=7&sekme=ziyaretler");
        const grup = within(
            await screen.findByRole("group", { name: "Süzgeç" }),
        );
        const kutu = (ad: string) =>
            grup.getByRole("combobox", { name: ad }) as HTMLSelectElement;
        await waitFor(() =>
            expect([...kutu("Huni").options].map((o) => o.textContent)).toEqual(
                ["Tüm huniler", "genel", "fitness"],
            ),
        );
        expect([...kutu("Ülke").options].map((o) => o.textContent)).toEqual([
            "Tüm ülkeler",
            "Türkiye",
            "Almanya",
        ]);
        expect(grup.queryByRole("combobox", { name: "Varyant" })).toBeNull();
        expect(
            grup.queryByRole("button", { name: "Süzgeçleri temizle" }),
        ).toBeNull();

        fireEvent.change(kutu("Huni"), { target: { value: "genel" } });
        await waitFor(() =>
            expect(son("/panel/api/rapor").get("huni")).toBe("genel"),
        );
        await waitFor(() =>
            expect(son("/panel/api/rapor/ziyaretler").get("huni")).toBe(
                "genel",
            ),
        );
        expect(
            screen.getByRole("link", { name: "90 gün" }).getAttribute("href"),
        ).toBe("/panel?gun=90&sekme=ziyaretler&huni=genel");
        expect(
            screen
                .getByRole("link", { name: "Kaynaklar" })
                .getAttribute("href"),
        ).toBe("/panel?gun=7&sekme=kaynaklar&huni=genel");
        // Üç listenin üçü de süzülür (kırma B2 ilk koşuda tutmadı: yalnız ziyaretlere bakılıyordu).
        fireEvent.click(screen.getByRole("link", { name: "Kaynaklar" }));
        await waitFor(() =>
            expect(son("/panel/api/rapor/kaynaklar").get("huni")).toBe("genel"),
        );
        fireEvent.click(screen.getByRole("link", { name: "Anket" }));
        await waitFor(() =>
            expect(son("/panel/api/rapor/serbestYanitlar").get("huni")).toBe(
                "genel",
            ),
        );

        fireEvent.change(kutu("Deney"), { target: { value: "d1" } });
        await waitFor(() =>
            expect(
                [...kutu("Varyant").options].map((o) => o.textContent),
            ).toEqual(["Tüm varyantlar", "a", "b"]),
        );
        fireEvent.change(kutu("Varyant"), { target: { value: "b" } });
        await waitFor(() =>
            expect(son("/panel/api/rapor").get("varyant")).toBe("b"),
        );
        fireEvent.change(kutu("Deney"), { target: { value: "" } });
        await waitFor(() =>
            expect(son("/panel/api/rapor").get("deney")).toBeNull(),
        );
        expect(son("/panel/api/rapor").get("varyant")).toBeNull();
        expect(grup.queryByRole("combobox", { name: "Varyant" })).toBeNull();

        fireEvent.click(
            grup.getByRole("button", { name: "Süzgeçleri temizle" }),
        );
        await waitFor(() =>
            expect(son("/panel/api/rapor").get("huni")).toBeNull(),
        );
        expect(
            screen.getByRole("link", { name: "90 gün" }).getAttribute("href"),
        ).toBe("/panel?gun=90&sekme=anket");

        // Dönemde görülmeyen seçili değer listede kalır.
        cleanup();
        kur("/panel?kaynak=tiktok");
        const k = within(
            await screen.findByRole("group", { name: "Süzgeç" }),
        ).getByRole("combobox", { name: "Kaynak" }) as HTMLSelectElement;
        await waitFor(() =>
            expect([...k.options].map((o) => o.value)).toEqual([
                "",
                "tiktok",
                "meta",
                "doğrudan",
            ]),
        );
        expect(k.value).toBe("tiktok");
    });
});
