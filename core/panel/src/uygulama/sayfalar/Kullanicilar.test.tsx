// @vitest-environment happy-dom
// P5: Kullanıcılar — satırlar, maskeli e-posta, durum rozeti, arama ve süzgeç
// adreste ve uca gider, başlıkla sıralama (aria-sort), boş sonuç.
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
import { yollar } from "../main";

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
        sonFatura: { paraBirimi: "EUR", tutar: 9.99, iade: 3 },
    }),
    satir("sub_c", {
        durum: "past_due",
        email: "z***@x.com",
        guncelleme: "2026-11-01T09:00:00+00:00",
        kaynak: "bulten",
        kampanya: "kis",
        iptalDonemSonu: true,
        donemSonu: "2026-12-01T10:00:00+00:00",
        sonFatura: { paraBirimi: "EUR", tutar: 9.99, iade: 9.99 },
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

function kur(satirlar = SATIRLAR, adres = "/panel/kullanicilar") {
    istenen = [];
    islemler = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            if (istek.method !== "GET") {
                islemler.push(
                    `${istek.method} ${new URL(istek.url, "http://x").pathname} ${istek.headers.has("X-CSRF-Token")}`,
                );
                return islemYaniti ?? new Response(null, { status: 204 });
            }
            istenen.push(new URL(istek.url, "http://x").search);
            return new Response(
                JSON.stringify({
                    azami: 200,
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

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    islemYaniti = null;
});

const islemHucresi = (abonelik: string): HTMLElement =>
    document.querySelector<HTMLElement>(
        `tr[data-abonelik="${abonelik}"] td:last-child`,
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
            "Son faturayı iade et (€6,99)",
        ]);
        expect(metin("sub_c")).toEqual(["İptali geri al"]);
        expect(islemHucresi("sub_c").textContent).toContain(
            "İptal planlı — 2026-12-01 10:00",
        );
        expect(metin("sub_b")).toEqual([]);
    });

    it("iki adımlı onay: Vazgeç istek göndermez, Evet jetonla gönderir ve listeyi yeniler", async () => {
        kur();
        await screen.findAllByRole("row");
        const hucre = within(islemHucresi("sub_a"));
        fireEvent.click(
            hucre.getByRole("button", { name: "Dönem sonunda iptal et" }),
        );
        expect(hucre.getByText(/emin misiniz/)).toBeTruthy();
        fireEvent.click(hucre.getByRole("button", { name: "Vazgeç" }));
        expect(islemler).toEqual([]);

        const once = istenen.length;
        fireEvent.click(
            hucre.getByRole("button", { name: /Son faturayı iade et/ }),
        );
        fireEvent.click(hucre.getByRole("button", { name: "Evet" }));
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
            within(islemHucresi("sub_c")).getByRole("button", { name: "Evet" }),
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
        const hucre = within(islemHucresi("sub_a"));
        fireEvent.click(
            hucre.getByRole("button", { name: "Dönem sonunda iptal et" }),
        );
        fireEvent.click(hucre.getByRole("button", { name: "Evet" }));
        expect((await hucre.findByRole("alert")).textContent).toBe(
            "Stripe isteği reddetti: x",
        );
    });
});

describe("P5: Kullanıcılar", () => {
    it("satırlar: maskeli e-posta, durum, kaynak + reklam, kişi bağlantısı; menüde işaretli", async () => {
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
        expect(a.getByText("bağlı (keepchat)")).toBeTruthy();
        expect(a.getByText("2026-11-01 10:00")).toBeTruthy();
        expect(
            within(
                document.querySelector<HTMLElement>(
                    'tr[data-abonelik="sub_c"]',
                )!,
            ).getByText("past_due"),
        ).toBeTruthy();
        expect(sira()).toEqual(["sub_a", "sub_c", "sub_b"]);
    });

    it("arama ve durum adrese yazılır ve uca gider", async () => {
        kur();
        await screen.findByText("3 abonelik");
        fireEvent.change(screen.getByLabelText("Ara"), {
            target: { value: " meta " },
        });
        fireEvent.submit(screen.getByRole("search"));
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe("?ara=meta"),
        );
        fireEvent.change(screen.getByLabelText("Durum"), {
            target: { value: "canceled" },
        });
        await waitFor(() =>
            expect(yonlendirici.state.location.search).toBe(
                "?ara=meta&durum=canceled",
            ),
        );
        await waitFor(() =>
            expect(istenen.at(-1)).toBe("?ara=meta&durum=canceled"),
        );
    });

    it("başlıkla sıralama: önce artan, ikinci tıkta azalan; aria-sort", async () => {
        kur();
        await screen.findByText("3 abonelik");
        const odeme = screen.getByRole("button", { name: "Ödeme (UTC)" });
        fireEvent.click(odeme);
        expect(sira()).toEqual(["sub_c", "sub_b", "sub_a"]);
        expect(odeme.closest("th")?.getAttribute("aria-sort")).toBe(
            "ascending",
        );
        fireEvent.click(odeme);
        expect(sira()).toEqual(["sub_a", "sub_b", "sub_c"]);
        expect(odeme.closest("th")?.getAttribute("aria-sort")).toBe(
            "descending",
        );
        fireEvent.click(screen.getByRole("button", { name: "E-posta" }));
        expect(sira()).toEqual(["sub_a", "sub_b", "sub_c"]);
    });

    it("eşleşme yoksa boş satır", async () => {
        kur([], "/panel/kullanicilar?ara=yok");
        await screen.findByText("Eşleşen abonelik yok.");
        expect((screen.getByLabelText("Ara") as HTMLInputElement).value).toBe(
            "yok",
        );
    });
});
