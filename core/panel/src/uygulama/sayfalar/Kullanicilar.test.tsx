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
    }),
    satir("sub_c", {
        durum: "past_due",
        email: "z***@x.com",
        guncelleme: "2026-11-01T09:00:00+00:00",
        kaynak: "bulten",
        kampanya: "kis",
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

function kur(satirlar = SATIRLAR, adres = "/panel/kullanicilar") {
    istenen = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
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
});

describe("P5: Kullanıcılar", () => {
    it("satırlar: maskeli e-posta, durum, kaynak + reklam, kişi bağlantısı; menüde işaretli", async () => {
        kur();
        await screen.findByText("3 abonelik");
        expect(
            screen
                .getByRole("link", { name: "Kullanıcılar" })
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
