// @vitest-environment happy-dom
// M7b: Webhook'lar — ekleme (olay seçilmeden düğme kapalı, sır bir kez),
// etkin / pasif, iki adımlı silme, teslim günlüğü ve yeniden gönder.
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

const HEDEF = {
    id: "11111111-1111-4111-8111-111111111111",
    adres: "https://alici.example.com/kanca",
    olaylar: ["odeme.fatura"],
    aktif: true,
    sirSonu: "Ab3=",
    bekleyen: 1,
    teslim: 4,
    vazgecildi: 1,
};
let yazilan: string[] = [];

function kur() {
    yazilan = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const yol = new URL(istek.url, "http://x").pathname;
            const json = (g: unknown, status = 200) =>
                new Response(JSON.stringify(g), {
                    status,
                    headers: { "Content-Type": "application/json" },
                });
            if (istek.method !== "GET") {
                yazilan.push(
                    `${istek.method} ${yol} ${istek.method === "DELETE" ? "" : await istek.text()}`.trim(),
                );
                return yol.endsWith("/webhooklar")
                    ? json({ id: HEDEF.id, sir: "whsec_YENI" }, 201)
                    : new Response(null, { status: 204 });
            }
            if (yol === "/panel/api/uygulamalar")
                return json({ uygulamalar: ["keepchat"] });
            if (yol.endsWith("/teslimler"))
                return json({
                    teslimler: [
                        {
                            id: 7,
                            olay: "odeme.fatura",
                            durum: "vazgecildi",
                            deneme: 10,
                            sonHata: "HTTP 500",
                            zaman: "2026-11-09T10:00:00+00:00",
                        },
                        {
                            id: 6,
                            olay: "odeme.fatura",
                            durum: "bekliyor",
                            deneme: 1,
                            sonHata: null,
                            zaman: "2026-11-09T09:00:00+00:00",
                        },
                    ],
                });
            return json({
                hedefler: [HEDEF],
                olaylar: ["huni.ziyaret", "odeme.fatura", "abonelik.durum"],
            });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/webhooklar"],
                })}
            />
        </QueryClientProvider>,
    );
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe("M7b — Webhook'lar", () => {
    it("ekle: olay seçilmeden kapalı; gövde kırpılmış adres + seçilen olaylar; sır bir kez görünür", async () => {
        kur();
        const ekle = await screen.findByRole("button", { name: "Ekle" });
        expect((ekle as HTMLButtonElement).disabled).toBe(true);
        fireEvent.change(
            screen.getByPlaceholderText("https://ornek.com/kanca"),
            {
                target: { value: "  https://yeni.example.com/k " },
            },
        );
        fireEvent.click(screen.getByLabelText("abonelik.durum"));
        fireEvent.click(screen.getByLabelText("huni.ziyaret"));
        fireEvent.click(ekle);
        await waitFor(() =>
            expect(yazilan).toEqual([
                'POST /panel/api/uygulamalar/keepchat/webhooklar {"adres":"https://yeni.example.com/k","olaylar":["abonelik.durum","huni.ziyaret"]}',
            ]),
        );
        expect((await screen.findByRole("alert")).textContent).toContain(
            "whsec_YENI",
        );
        expect(
            screen.getByRole("navigation", { name: "Panel" }).textContent,
        ).toContain("Webhook'lar");
    });

    it("pasifleştir ve iki adımlı silme", async () => {
        kur();
        const satir = within(
            (await screen.findByText(HEDEF.adres)).closest("tr") as HTMLElement,
        );
        expect(
            satir.getByText("4 teslim · 1 bekliyor · 1 vazgeçildi"),
        ).toBeTruthy();
        fireEvent.click(satir.getByRole("button", { name: "Pasifleştir" }));
        fireEvent.click(satir.getByRole("button", { name: "Sil" }));
        expect(satir.getByText("Silinsin mi?")).toBeTruthy();
        await waitFor(() => expect(yazilan).toHaveLength(1));
        fireEvent.click(satir.getByRole("button", { name: "Evet" }));
        await waitFor(() =>
            expect(yazilan).toEqual([
                `PUT /panel/api/uygulamalar/keepchat/webhooklar/${HEDEF.id} {"aktif":false}`,
                `DELETE /panel/api/uygulamalar/keepchat/webhooklar/${HEDEF.id}`,
            ]),
        );
    });

    it("günlük: yeniden gönder yalnız bekleyen olmayanda, doğru teslime", async () => {
        kur();
        fireEvent.click(await screen.findByRole("button", { name: "Günlük" }));
        const gunluk = within(
            await screen.findByRole("table", { name: "Teslim günlüğü" }),
        );
        const satirlar = gunluk.getAllByRole("row");
        expect(satirlar.map((s) => s.textContent)).toEqual([
            "2026-11-09 10:00odeme.faturavazgecildi (10) — HTTP 500Yeniden gönder",
            "2026-11-09 09:00odeme.faturabekliyor (1)",
        ]);
        fireEvent.click(gunluk.getByRole("button", { name: "Yeniden gönder" }));
        await waitFor(() =>
            expect(yazilan).toEqual([
                `POST /panel/api/uygulamalar/keepchat/webhooklar/${HEDEF.id}/teslimler/7/yeniden`,
            ]),
        );
    });
});
