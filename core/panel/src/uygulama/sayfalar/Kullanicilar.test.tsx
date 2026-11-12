// @vitest-environment happy-dom
// P5: Aboneler — satırlar, maskeli e-posta, durum, arama ve süzgeç adreste ve
// uca gider, başlıkla sıralama (aria-sort), boş sonuç. P7a: Türkçe tarihli
// durum, bölmeli durum süzgeci, satır altında sonucu anlatan onay.
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
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { yollar } from "../main";
import { ciplakAlanlar } from "../ui/stilDenetimi";

const satir = (abonelik: string, ek: object) => ({
    abonelik,
    app: "keepchat",
    durum: "active",
    email: null,
    guncelleme: "2026-11-02T09:00:00+00:00",
    odeme: null,
    reklam: false,
    kaynak: null,
    ortam: null,
    kampanya: null,
    huni: null,
    kisi: false,
    bagli: false,
    iptalDonemSonu: false,
    donemSonu: null,
    duraklatildi: false,
    duraklatmaBitis: null,
    sonFatura: null,
    ...ek,
});
const SATIRLAR = [
    satir("sub_a", {
        email: "a***@example.com",
        odeme: "2026-11-01T10:00:00+00:00",
        reklam: true,
        kaynak: "meta",
        ortam: "paid_social",
        kampanya: "ekim-1",
        huni: "genel",
        kisi: true,
        bagli: true,
        donemSonu: "2026-12-01T10:00:00+00:00",
        sonFatura: {
            paraBirimi: "EUR",
            tutar: 9.99,
            iade: 3,
            odendi: "2026-11-03T10:00:00+00:00",
        },
    }),
    satir("sub_c", {
        durum: "past_due",
        email: "z***@x.com",
        guncelleme: "2026-11-01T09:00:00+00:00",
        kaynak: "bulten",
        kampanya: "kis",
        iptalDonemSonu: true,
        donemSonu: "2026-12-01T10:00:00+00:00",
        sonFatura: {
            paraBirimi: "EUR",
            tutar: 9.99,
            iade: 9.99,
            odendi: "2026-10-21T10:00:00+00:00",
        },
    }),
    satir("sub_b", {
        durum: "canceled",
        email: "m***@ornek.com",
        guncelleme: "2026-10-30T09:00:00+00:00",
        odeme: "2026-10-01T08:00:00+00:00",
    }),
];

let yonlendirici: ReturnType<typeof createMemoryRouter>;
let istenen: string[] = [];
let islemler: string[] = [];
let islemYaniti: Response | null = null;
let toplam: number | null = null;

function kur(satirlar = SATIRLAR, adres = "/panel/kullanicilar") {
    istenen = [];
    islemler = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            if (istek.method !== "GET") {
                // M6c: gövde varsa (duraklatma süresi) sona eklenir.
                const govde = await istek.text();
                islemler.push(
                    `${istek.method} ${new URL(istek.url, "http://x").pathname} ${istek.headers.has("X-CSRF-Token")}${govde ? ` ${govde}` : ""}`,
                );
                return islemYaniti ?? new Response(null, { status: 204 });
            }
            const adres = new URL(istek.url, "http://x");
            istenen.push(adres.search);
            return new Response(
                JSON.stringify({
                    sayfa: Number(adres.searchParams.get("sayfa") ?? 1),
                    boyut: 25,
                    toplam: toplam ?? satirlar.length,
                    durumlar: ["active", "canceled", "past_due"],
                    satirlar,
                }),
                { status: 200 },
            );
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={
                    (yonlendirici = createMemoryRouter(yollar, {
                        initialEntries: [adres],
                    }))
                }
            />
        </QueryClientProvider>,
    );
}
const sira = (): string[] =>
    [...document.querySelectorAll("tr[data-abonelik]")].map(
        (t) => t.getAttribute("data-abonelik") ?? "",
    );

// Tarih yılı "bugün"e göre yazılır (3 Kas / 3 Kas 2025) — saat sabit.
beforeEach(() => {
    vi.useFakeTimers({
        toFake: ["Date"],
        now: new Date("2026-11-10T12:00:00Z"),
    });
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    islemYaniti = null;
    toplam = null;
});

const islemHucresi = (abonelik: string): HTMLElement =>
    document.querySelector<HTMLElement>(
        `tr[data-abonelik="${abonelik}"] td:last-child`,
    )!;
// Onay ve hata satırı abonelik satırının hemen altında.
const altSatir = (abonelik: string): HTMLElement =>
    document.querySelector<HTMLElement>(
        `tr[data-abonelik="${abonelik}"] + tr:not([data-abonelik])`,
    )!;

describe("M6a — abonelik işlemleri", () => {
    it("düğmeler duruma göre: sürende iptal, planlıda geri al, kalan iade tutarıyla", async () => {
        kur();
        await screen.findAllByRole("row");
        const metin = (a: string) =>
            [...islemHucresi(a).querySelectorAll("button")].map(
                (b) => b.textContent,
            );
        // 9,99 − 3 = 6,99 kalan; tam iade edilmiş faturada iade düğmesi yok.
        expect(metin("sub_a")).toEqual([
            "Dönem sonunda iptal et",
            "Duraklat",
            "€6,99 iade et",
        ]);
        expect(metin("sub_c")).toEqual(["İptali geri al"]);
        expect(metin("sub_b")).toEqual([]);
    });

    it("denemede: düğme ve onay deneme sonunu söyler", async () => {
        kur([
            satir("sub_d", {
                durum: "trialing",
                donemSonu: "2026-11-15T10:00:00+00:00",
            }),
        ]);
        await screen.findAllByRole("row");
        expect(
            document.querySelector('tr[data-abonelik="sub_d"]')?.textContent,
        ).toContain("Denemede · 15 Kasım'da biter");
        fireEvent.click(
            within(islemHucresi("sub_d")).getByRole("button", {
                name: "Deneme sonunda iptal et",
            }),
        );
        expect(altSatir("sub_d").textContent).toContain(
            "15 Kasım'a kadar açık kalır, sonra ücret çekilmez",
        );
    });

    it("iki adımlı onay satırın altında: Vazgeç istek göndermez, onay jetonla gönderir ve listeyi yeniler", async () => {
        kur();
        await screen.findAllByRole("row");
        const hucre = within(islemHucresi("sub_a"));
        fireEvent.click(
            hucre.getByRole("button", { name: "Dönem sonunda iptal et" }),
        );
        expect(altSatir("sub_a").textContent).toContain(
            "Dönem sonunda iptal edilsin mi? a***@example.com · 1 Aralık'a kadar açık kalır, sonra yenilenmez.",
        );
        fireEvent.click(
            within(altSatir("sub_a")).getByRole("button", { name: "Vazgeç" }),
        );
        expect(altSatir("sub_a")).toBeNull();
        expect(islemler).toEqual([]);

        const once = istenen.length;
        fireEvent.click(hucre.getByRole("button", { name: "€6,99 iade et" }));
        expect(altSatir("sub_a").textContent).toContain(
            "€6,99 iade edilsin mi? a***@example.com · 3 Kasım faturası. Geri alınamaz; abonelik sürer.",
        );
        fireEvent.click(
            within(altSatir("sub_a")).getByRole("button", { name: "İade et" }),
        );
        await waitFor(() =>
            expect(islemler).toEqual([
                "POST /panel/api/abonelikler/sub_a/iade true",
            ]),
        );
        await waitFor(() => expect(istenen.length).toBeGreaterThan(once));

        fireEvent.click(
            within(islemHucresi("sub_c")).getByRole("button", {
                name: "İptali geri al",
            }),
        );
        fireEvent.click(
            within(altSatir("sub_c")).getByRole("button", { name: "Geri al" }),
        );
        await waitFor(() =>
            expect(islemler.at(-1)).toBe(
                "DELETE /panel/api/abonelikler/sub_c/iptal true",
            ),
        );
    });

    it("Stripe reddederse sunucunun mesajı satırda", async () => {
        islemYaniti = new Response(
            JSON.stringify({ hatalar: ["Stripe isteği reddetti: x"] }),
            { status: 422, headers: { "Content-Type": "application/json" } },
        );
        kur();
        await screen.findAllByRole("row");
        fireEvent.click(
            within(islemHucresi("sub_a")).getByRole("button", {
                name: "Dönem sonunda iptal et",
            }),
        );
        fireEvent.click(
            within(altSatir("sub_a")).getByRole("button", { name: "İptal et" }),
        );
        await waitFor(() => expect(altSatir("sub_a")).not.toBeNull());
        expect(
            (await within(altSatir("sub_a")).findByRole("alert")).textContent,
        ).toBe("Stripe isteği reddetti: x");
    });
});

describe("P5: Aboneler", () => {
    it("satırlar: maskeli e-posta, Türkçe durum, kaynak + reklam, kişi bağlantısı, son ödeme; menüde işaretli", async () => {
        kur();
        await screen.findByText("3 abonelik");
        expect(
            screen
                .getByRole("link", { name: "Aboneler" })
                .getAttribute("aria-current"),
        ).toBe("page");
        const a = within(
            document.querySelector<HTMLElement>('tr[data-abonelik="sub_a"]')!,
        );
        expect(a.getByText("a***@example.com")).toBeTruthy();
        expect(a.getByText("reklam")).toBeTruthy();
        expect(a.getByText(/meta \/ paid_social/)).toBeTruthy();
        expect(
            a.getByText("1 Kasım'dan beri · uygulamaya bağlı (keepchat)"),
        ).toBeTruthy();
        expect(a.getByText("Aktif")).toBeTruthy();
        expect(a.getByText("ekim-1 · genel")).toBeTruthy();
        expect(a.getByText("€9,99 · 3 Kas")).toBeTruthy();
        expect(a.getByText("€3,00 iade edildi")).toBeTruthy();
        const c = within(
            document.querySelector<HTMLElement>('tr[data-abonelik="sub_c"]')!,
        );
        // İptali planlı abonelik Stripe'ta hâlâ past_due: önce iptal söylenir.
        expect(
            c.getByText("İptal edildi · 1 Aralık'a kadar açık"),
        ).toBeTruthy();
        expect(c.getByText("İade edildi")).toBeTruthy();
        // Ödeme anı bilinmiyor: alt satırda tarih ya da tahmin yok.
        expect(
            document.querySelector('tr[data-abonelik="sub_c"] td span.text-xs')
                ?.textContent,
        ).toBe("");
        const b = within(
            document.querySelector<HTMLElement>('tr[data-abonelik="sub_b"]')!,
        );
        expect(b.getByText("Bitti")).toBeTruthy();
        expect(b.getByText("Henüz yok")).toBeTruthy();
        expect(b.getByText("Doğrudan")).toBeTruthy();
        expect(sira()).toEqual(["sub_a", "sub_c", "sub_b"]);
    });

    it("arama ve durum adrese yazılır ve uca gider", async () => {
        kur();
        await screen.findByText("3 abonelik");
        fireEvent.change(screen.getByLabelText("E-posta ya da kampanya ara"), {
            target: { value: " meta " },
        });
        fireEvent.submit(screen.getByRole("search"));
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?ara=meta"),
        );
        const durum = within(screen.getByRole("group", { name: "Durum" }));
        expect(
            durum
                .getByRole("button", { name: "Tümü" })
                .getAttribute("aria-pressed"),
        ).toBe("true");
        fireEvent.click(durum.getByRole("button", { name: "Bitti" }));
        await waitFor(() =>
            expect(
                durum
                    .getByRole("button", { name: "Bitti" })
                    .getAttribute("aria-pressed"),
            ).toBe("true"),
        );
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe(
                "?ara=meta&durum=canceled",
            ),
        );
        await waitFor(() =>
            expect(istenen.at(-1)).toBe("?ara=meta&durum=canceled&sayfa=1"),
        );
    });

    it("P7a2: başlıkla sıralama sunucuda — adreste ve uçta; ikinci tık azalan; sayfa başa döner", async () => {
        kur(SATIRLAR, "/panel/kullanicilar?sayfa=2");
        await screen.findAllByRole("row");
        const odeme = screen.getByRole("button", { name: "Son ödeme" });
        fireEvent.click(odeme);
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?sirala=sonOdeme"),
        );
        await waitFor(() =>
            expect(istenen.at(-1)).toBe(
                "?ara=&durum=&sayfa=1&sirala=sonOdeme&yon=artan",
            ),
        );
        expect(odeme.closest("th")?.getAttribute("aria-sort")).toBe(
            "ascending",
        );
        fireEvent.click(odeme);
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe(
                "?sirala=sonOdeme&yon=azalan",
            ),
        );
        expect(odeme.closest("th")?.getAttribute("aria-sort")).toBe(
            "descending",
        );
        fireEvent.click(screen.getByRole("button", { name: "Abone" }));
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?sirala=email"),
        );
    });

    it("P7a2: sayfalama — sayı, sonraki sayfa adreste ve uçta, süzgeç ilk sayfaya döndürür", async () => {
        toplam = 30;
        kur();
        const nav = await screen.findByRole("navigation", {
            name: "Sayfalama",
        });
        // Aralık sunucunun sayfa boyutuyla (25) yazılır.
        expect(nav.textContent).toContain("1–25 / 30 abonelik");
        fireEvent.click(within(nav).getByRole("button", { name: "Sonraki ›" }));
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?sayfa=2"),
        );
        await waitFor(() =>
            expect(istenen.at(-1)).toBe("?ara=&durum=&sayfa=2"),
        );
        fireEvent.click(
            within(screen.getByRole("group", { name: "Durum" })).getByRole(
                "button",
                { name: "Aktif" },
            ),
        );
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?durum=active"),
        );
    });

    it("eşleşme yoksa boş satır", async () => {
        kur([], "/panel/kullanicilar?ara=yok");
        await screen.findByText("Eşleşen abonelik yok.");
        expect(
            (
                screen.getByLabelText(
                    "E-posta ya da kampanya ara",
                ) as HTMLInputElement
            ).value,
        ).toBe("yok");
    });

    it("P7e: her alan ortak stilde (ui/Alan), her kutu vurgu renginde", async () => {
        kur();
        await screen.findAllByRole("row");
        expect(ciplakAlanlar(document.body)).toEqual([]);
    });
});

describe("M6c — duraklat / sürdür", () => {
    it("duraklatılmış satır: durum bitişle, Sürdür DELETE ile; iptal planlıda Duraklat yok", async () => {
        kur([
            satir("sub_e", {
                email: "e***@x.com",
                duraklatildi: true,
                duraklatmaBitis: "2027-01-10T10:00:00+00:00",
            }),
            ...SATIRLAR,
        ]);
        await screen.findAllByRole("row");
        expect(
            document.querySelector('tr[data-abonelik="sub_e"]')?.textContent,
        ).toContain("Duraklatıldı · 10 Ocak 2027'ye kadar");
        const dugmeler = (a: string) =>
            [...islemHucresi(a).querySelectorAll("button")].map(
                (b) => b.textContent,
            );
        expect(dugmeler("sub_e")).toEqual(["Dönem sonunda iptal et", "Sürdür"]);
        expect(dugmeler("sub_c")).toEqual(["İptali geri al"]);

        fireEvent.click(
            within(islemHucresi("sub_e")).getByRole("button", {
                name: "Sürdür",
            }),
        );
        expect(altSatir("sub_e").textContent).toContain(
            "Abonelik sürdürülsün mü? e***@x.com · erişim hemen açılır",
        );
        fireEvent.click(
            within(altSatir("sub_e")).getByRole("button", { name: "Sürdür" }),
        );
        await waitFor(() =>
            expect(islemler).toEqual([
                "DELETE /panel/api/abonelikler/sub_e/duraklat true",
            ]),
        );
    });

    it("duraklat: onayda süre seçilir (varsayılan 1 ay), seçilen ay gövdede jetonla gider", async () => {
        kur();
        await screen.findAllByRole("row");
        fireEvent.click(
            within(islemHucresi("sub_a")).getByRole("button", {
                name: "Duraklat",
            }),
        );
        const onay = within(altSatir("sub_a"));
        expect(altSatir("sub_a").textContent).toContain(
            "Kaç ay duraklatılsın? a***@example.com · bu sürede ücret çekilmez ve uygulamaya erişemez",
        );
        const sure = within(onay.getByRole("group", { name: "Süre" }));
        expect(
            sure
                .getByRole("button", { name: "1 ay" })
                .getAttribute("aria-pressed"),
        ).toBe("true");
        fireEvent.click(sure.getByRole("button", { name: "3 ay" }));
        fireEvent.click(onay.getByRole("button", { name: "Duraklat" }));
        await waitFor(() =>
            expect(islemler).toEqual([
                'POST /panel/api/abonelikler/sub_a/duraklat true {"ay":3}',
            ]),
        );
    });
});

describe("R2 — CSV indir", () => {
    it("listenin arama, durum ve sırasıyla; sayfa yok", async () => {
        kur(
            SATIRLAR,
            "/panel/kullanicilar?ara=meta&durum=active&sayfa=2&sirala=email&yon=azalan",
        );
        await screen.findAllByRole("row");
        const bag = screen.getByRole("link", { name: "CSV indir" });
        expect(bag.getAttribute("href")).toBe(
            "/panel/api/kullanicilar.csv?ara=meta&durum=active&sirala=email&yon=azalan",
        );
        expect(bag.hasAttribute("download")).toBe(true);
    });
});
