// @vitest-environment happy-dom
// M1a: Planlar sayfası — liste fiyatı Stripe'tan gelen yanıttan yazar,
// okunamayan fiyatı söyler; yeni plan ve fiyat değişimi tutarı SAYI olarak
// ve kabuğun jetonuyla gönderir; çekirdeğin Türkçe hatası görünür.
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

type Istek = {
    yontem: string;
    adres: string;
    jeton: string | null;
    govde: unknown;
};

function kur(yanit422?: string) {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    let planlar: unknown[] = [
        {
            anahtar: "monthly",
            ad: "Aylık",
            stripeFiyat: "price_a",
            fiyat: { tutar: 9.99, paraBirimi: "EUR", aralik: "month" },
        },
        { anahtar: "eski", ad: "Eski", stripeFiyat: "price_b", fiyat: null },
    ];
    const istekler: Istek[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const adres = new URL(istek.url, "http://x").pathname;
            const govde = istek.method === "GET" ? null : await istek.json();
            istekler.push({
                yontem: istek.method,
                adres,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde,
            });
            const j = (o: unknown, durum = 200) =>
                new Response(JSON.stringify(o), { status: durum });
            if (adres === "/panel/api/uygulamalar")
                return j({ uygulamalar: ["keepchat"] });
            if (yanit422 && istek.method !== "GET")
                return j({ hatalar: [yanit422] }, 422);
            if (istek.method === "POST") {
                planlar = [
                    ...planlar,
                    {
                        anahtar: "yearly",
                        ad: "Yıllık",
                        stripeFiyat: "price_c",
                        fiyat: { tutar: 59, paraBirimi: "USD", aralik: "year" },
                    },
                ];
                return j({ planlar }, 201);
            }
            if (istek.method === "PUT") {
                planlar = planlar.map((p, i) =>
                    i === 0
                        ? {
                              ...(p as object),
                              stripeFiyat: "price_yeni",
                              fiyat: {
                                  tutar: 12.5,
                                  paraBirimi: "EUR",
                                  aralik: "month",
                              },
                          }
                        : p,
                );
                return j({ planlar });
            }
            return j({ planlar });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/planlar"],
                })}
            />
        </QueryClientProvider>,
    );
    return istekler;
}

afterEach(() => {
    cleanup();
    document.getElementById("panel-uygulama")?.remove();
    vi.unstubAllGlobals();
});

describe("Planlar", () => {
    it("fiyatı yanıttan biçimler; okunamayan fiyatı söyler", async () => {
        kur();
        const tablo = await screen.findByRole("region", { name: "Planlar" });
        // Beklenen metin elle (tuzak §3): tr-TR para biçimi + "/ ay".
        expect(await within(tablo).findByText("€9,99 / ay")).toBeTruthy();
        expect(within(tablo).getByText("Stripe'tan okunamadı")).toBeTruthy();
    });

    it("yeni plan: tutar sayı olarak, kabuğun jetonuyla; liste yanıttan yenilenir", async () => {
        const istekler = kur();
        fireEvent.change(await screen.findByLabelText("Anahtar"), {
            target: { value: "yearly" },
        });
        fireEvent.change(screen.getByLabelText("Ad"), {
            target: { value: "Yıllık" },
        });
        fireEvent.change(screen.getByLabelText("Tutar"), {
            target: { value: "59" },
        });
        fireEvent.change(screen.getByLabelText("Para birimi"), {
            target: { value: "USD" },
        });
        fireEvent.change(screen.getByLabelText("Aralık"), {
            target: { value: "year" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Planı oluştur" }));

        // USD simgesi ICU sürümüne göre "$" ya da "US$"; ölçülen tutar + aralık.
        await screen.findByText(
            (t) => t.includes("59,00") && t.endsWith("/ yıl"),
        );
        expect(istekler.find((i) => i.yontem === "POST")).toEqual({
            yontem: "POST",
            adres: "/panel/api/uygulamalar/keepchat/planlar",
            jeton: "jeton-kabuk",
            govde: {
                anahtar: "yearly",
                ad: "Yıllık",
                tutar: 59,
                paraBirimi: "USD",
                aralik: "year",
            },
        });
    });

    it("fiyatı değiştir: yalnız tutar gider, satır yeni fiyatla", async () => {
        const istekler = kur();
        const tablo = await screen.findByRole("region", { name: "Planlar" });
        fireEvent.click(
            (
                await within(tablo).findAllByRole("button", {
                    name: "Fiyatı değiştir",
                })
            )[0]!,
        );
        fireEvent.change(within(tablo).getByLabelText("monthly yeni tutar"), {
            target: { value: "12.5" },
        });
        fireEvent.click(within(tablo).getByRole("button", { name: "Kaydet" }));

        await within(tablo).findByText("€12,50 / ay");
        expect(istekler.find((i) => i.yontem === "PUT")).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/planlar/monthly/fiyat",
            jeton: "jeton-kabuk",
            govde: { tutar: 12.5 },
        });
    });

    it("çekirdeğin hatası görünür", async () => {
        kur("'monthly' planı zaten var.");
        fireEvent.change(await screen.findByLabelText("Anahtar"), {
            target: { value: "monthly" },
        });
        fireEvent.change(screen.getByLabelText("Ad"), {
            target: { value: "X" },
        });
        fireEvent.change(screen.getByLabelText("Tutar"), {
            target: { value: "1" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Planı oluştur" }));
        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toBe(
                "'monthly' planı zaten var.",
            ),
        );
    });
});
