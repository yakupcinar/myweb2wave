// @vitest-environment happy-dom
// E1b: e-postalar sayfası — her şablon kendi kartında, dil seçimi, yer
// tutucular; "Kaydet" yalnız değişince ve doğru şablon/dil adresine kabuğun
// jetonuyla konu + metin gönderir; "Varsayılana dön" yalnız kayıtlıda ve
// DELETE'le; yanıt kartı yeniler; sunucu hatası satırda.
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
import { ciplakAlanlar } from "../ui/stilDenetimi";

const sablon = (
    s: "dogrulama_kodu" | "giris_baglantisi",
    dil: "tr" | "en",
    ek: object = {},
) => ({
    sablon: s,
    dil,
    ad: s === "dogrulama_kodu" ? "Doğrulama kodu" : "Giriş bağlantısı",
    aciklama:
        s === "dogrulama_kodu" ? "6 haneli kod." : "Ödeme sonrası bağlantı.",
    konu: `${s} ${dil} konu`,
    metin: `${s} ${dil} metin {{kod}}`,
    kayitli: false,
    guncellendi: null,
    yerTutucular:
        s === "dogrulama_kodu"
            ? ["kod", "dakika", "uygulama"]
            : ["baglanti", "saat", "uygulama"],
    zorunlu: s === "dogrulama_kodu" ? ["kod"] : ["baglanti"],
    ...ek,
});
const VARSAYILAN = [
    sablon("dogrulama_kodu", "tr"),
    sablon("dogrulama_kodu", "en"),
    sablon("giris_baglantisi", "tr"),
    sablon("giris_baglantisi", "en"),
];

type Istek = {
    yontem: string;
    adres: string;
    jeton: string | null;
    govde: unknown;
};

function kur(yaz?: (istek: Istek) => Response, ilk: object[] = VARSAYILAN) {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    const istekler: Istek[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const i: Istek = {
                yontem: istek.method,
                adres: new URL(istek.url, "http://x").pathname,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde: istek.method === "PUT" ? await istek.json() : null,
            };
            istekler.push(i);
            if (istek.method !== "GET")
                return yaz
                    ? yaz(i)
                    : new Response(JSON.stringify({ sablonlar: VARSAYILAN }));
            if (istek.url.endsWith("/panel/api/uygulamalar"))
                return new Response(
                    JSON.stringify({ uygulamalar: ["keepchat"] }),
                );
            return new Response(JSON.stringify({ sablonlar: ilk }));
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/epostalar"],
                })}
            />
        </QueryClientProvider>,
    );
    return istekler;
}

const kart = (ad: string) => screen.getByRole("region", { name: ad });

afterEach(() => {
    cleanup();
    document.getElementById("panel-uygulama")?.remove();
    vi.unstubAllGlobals();
});

describe("E-postalar", () => {
    it("şablon başına kart, varsayılan durumu, yer tutucular; menüde işaretli", async () => {
        kur();
        expect(
            await screen.findByDisplayValue("dogrulama_kodu tr konu"),
        ).toBeTruthy();
        expect(ciplakAlanlar(document.body)).toEqual([]);
        const kod = kart("Doğrulama kodu");
        expect(within(kod).getByText("Varsayılan metin")).toBeTruthy();
        expect(within(kod).getByText(/Kullanılabilir/).textContent).toBe(
            "Kullanılabilir: {{kod}}, {{dakika}}, {{uygulama}} · metinde olmalı: {{kod}}",
        );
        expect(
            within(kod).queryByRole("button", { name: "Varsayılana dön" }),
        ).toBeNull();
        expect(
            (
                within(kod).getByRole("button", {
                    name: "Kaydet",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        expect(
            within(kart("Giriş bağlantısı")).getByDisplayValue(
                "giris_baglantisi tr konu",
            ),
        ).toBeTruthy();
        expect(
            screen
                .getByRole("link", { name: "E-postalar" })
                .getAttribute("aria-current"),
        ).toBe("page");
    });

    it("dil seçilince o dilin metni; kaydet doğru adrese jetonla gider, kart düzenlendi olur", async () => {
        const istekler = kur(
            (i) =>
                new Response(
                    JSON.stringify({
                        sablonlar: VARSAYILAN.map((s) =>
                            s.sablon === "dogrulama_kodu" && s.dil === "en"
                                ? {
                                      ...s,
                                      ...(i.govde as object),
                                      kayitli: true,
                                      guncellendi: "2026-11-12T10:00:00+00:00",
                                  }
                                : s,
                        ),
                    }),
                ),
        );
        await screen.findByDisplayValue("dogrulama_kodu tr konu");
        const kod = kart("Doğrulama kodu");
        fireEvent.click(
            within(within(kod).getByRole("group", { name: "Dil" })).getByRole(
                "button",
                { name: "İngilizce" },
            ),
        );
        expect(
            within(kod).getByDisplayValue("dogrulama_kodu en metin {{kod}}"),
        ).toBeTruthy();

        fireEvent.change(within(kod).getByLabelText("Konu (İngilizce)"), {
            target: { value: "Your {{uygulama}} code" },
        });
        fireEvent.change(within(kod).getByLabelText("Metin (İngilizce)"), {
            target: { value: "Code: {{kod}}" },
        });
        fireEvent.click(within(kod).getByRole("button", { name: "Kaydet" }));

        await waitFor(() =>
            expect(
                within(kart("Doğrulama kodu")).getByText("Düzenlendi"),
            ).toBeTruthy(),
        );
        expect(istekler.filter((i) => i.yontem === "PUT")).toEqual([
            {
                yontem: "PUT",
                adres: "/panel/api/uygulamalar/keepchat/epostalar/dogrulama_kodu/en",
                jeton: "jeton-kabuk",
                govde: {
                    konu: "Your {{uygulama}} code",
                    metin: "Code: {{kod}}",
                },
            },
        ]);
        const yeni = kart("Doğrulama kodu");
        expect(within(yeni).getByDisplayValue("Code: {{kod}}")).toBeTruthy();
        expect(
            within(yeni).getByRole("button", { name: "Varsayılana dön" }),
        ).toBeTruthy();
        expect(
            (
                within(yeni).getByRole("button", {
                    name: "Kaydet",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        // Öbür kart etkilenmez.
        expect(
            within(kart("Giriş bağlantısı")).getByText("Varsayılan metin"),
        ).toBeTruthy();
    });

    it("varsayılana dön DELETE ile gider, kart varsayılan metne döner", async () => {
        const kayitli = {
            konu: "Özel konu",
            metin: "Özel {{baglanti}}",
            kayitli: true,
            guncellendi: "2026-11-12T10:00:00+00:00",
        };
        const istekler = kur(
            () => new Response(JSON.stringify({ sablonlar: VARSAYILAN })),
            VARSAYILAN.map((s) =>
                s.sablon === "giris_baglantisi" && s.dil === "tr"
                    ? { ...s, ...kayitli }
                    : s,
            ),
        );
        const giris = await screen.findByRole("region", {
            name: "Giriş bağlantısı",
        });
        expect(
            await within(giris).findByDisplayValue("Özel konu"),
        ).toBeTruthy();
        expect(within(giris).getByText("Düzenlendi")).toBeTruthy();
        fireEvent.click(
            within(giris).getByRole("button", { name: "Varsayılana dön" }),
        );

        await waitFor(() =>
            expect(
                within(kart("Giriş bağlantısı")).getByDisplayValue(
                    "giris_baglantisi tr konu",
                ),
            ).toBeTruthy(),
        );
        expect(
            within(kart("Giriş bağlantısı")).getByText("Varsayılan metin"),
        ).toBeTruthy();
        expect(istekler.filter((i) => i.yontem === "DELETE")).toEqual([
            {
                yontem: "DELETE",
                adres: "/panel/api/uygulamalar/keepchat/epostalar/giris_baglantisi/tr",
                jeton: "jeton-kabuk",
                govde: null,
            },
        ]);
    });

    it("sunucu hatası satırda", async () => {
        kur(
            () =>
                new Response(
                    JSON.stringify({ hatalar: ["metinde olmalı: {{kod}}"] }),
                    { status: 422 },
                ),
        );
        await screen.findByDisplayValue("dogrulama_kodu tr konu");
        const kod = kart("Doğrulama kodu");
        fireEvent.change(within(kod).getByLabelText("Metin (Türkçe)"), {
            target: { value: "kodsuz" },
        });
        fireEvent.click(within(kod).getByRole("button", { name: "Kaydet" }));
        await waitFor(() =>
            expect(within(kod).getByRole("alert").textContent).toBe(
                "metinde olmalı: {{kod}}",
            ),
        );
    });
});
