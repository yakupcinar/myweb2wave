// @vitest-environment happy-dom
// Ç152: İşlem geçmişi — sunucunun sırasıyla (yeniden eskiye) satırlar, rota
// okunur adla (bilinmeyen rota adıyla), kaynak "ad: değer", sonuç yapıldı /
// reddedildi + kod; menüde işaretli; yüklenemezse hata, boşsa "Henüz kayıt yok".
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, within } from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import { yollar } from "../main";

const KAYITLAR = [
    {
        zaman: "2026-11-19T09:15:00Z",
        kullanici: "yakup",
        yontem: "PUT",
        rota: "panel_api_plan_fiyat",
        kaynak: { anahtar: "aylik", app: "keepchat" },
        durum: 204,
    },
    {
        zaman: "2026-11-19T09:10:00Z",
        kullanici: "yakup",
        yontem: "POST",
        rota: "panel_api_anahtar_olustur",
        kaynak: { app: "keepchat" },
        durum: 403,
    },
    {
        zaman: "2026-11-19T09:00:00Z",
        kullanici: "yakup",
        yontem: "POST",
        rota: "panel_api_yeni_uc",
        kaynak: {},
        durum: 201,
    },
];

function kur(yanit: () => Response) {
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) =>
            istek.url.endsWith("/panel/api/denetim")
                ? yanit()
                : new Response(JSON.stringify({ uygulamalar: ["keepchat"] })),
        ),
    );
    render(
        <QueryClientProvider
            client={
                new QueryClient({
                    defaultOptions: { queries: { retry: false } },
                })
            }
        >
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/islem-gecmisi"],
                })}
            />
        </QueryClientProvider>,
    );
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe("İşlem geçmişi", () => {
    it("sunucunun sırasıyla okunur satırlar; menüde işaretli", async () => {
        kur(() => new Response(JSON.stringify({ kayitlar: KAYITLAR })));
        const tablo = await screen.findByRole("table");
        const satirlar = within(tablo).getAllByRole("row").slice(1);
        expect(
            satirlar.map((s) =>
                within(s)
                    .getAllByRole("cell")
                    .map((h) =>
                        (h.textContent ?? "").replace("Kullanıcı ", ""),
                    ),
            ),
        ).toEqual([
            [
                "2026-11-19 09:15",
                "yakup",
                "Plan fiyatı değiştirme",
                "anahtar: aylik · app: keepchat",
                "Yapıldı",
            ],
            [
                "2026-11-19 09:10",
                "yakup",
                "API anahtarı oluşturma",
                "app: keepchat",
                "Reddedildi · 403",
            ],
            ["2026-11-19 09:00", "yakup", "panel_api_yeni_uc", "—", "Yapıldı"],
        ]);
        expect(
            screen
                .getByRole("link", { name: "İşlem geçmişi" })
                .getAttribute("aria-current"),
        ).toBe("page");
    });

    it("boşsa söyler, yüklenemezse hata", async () => {
        kur(() => new Response(JSON.stringify({ kayitlar: [] })));
        expect(await screen.findByText("Henüz kayıt yok.")).toBeTruthy();
        cleanup();
        kur(() => new Response("{}", { status: 500 }));
        expect((await screen.findByRole("alert")).textContent).toBe(
            "İşlem geçmişi yüklenemedi.",
        );
    });
});
