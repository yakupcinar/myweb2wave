// @vitest-environment happy-dom
// A4: ürün bağlamı sayfası — yükler, düzenler, kabuğun CSRF jetonuyla PUT
// eder; hata gösterir; menüde işaretli. Sunucu yerine sahte fetch.
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import { yollar } from "../main";

const KAYITLI = {
    urun: "Sade mesajlaşma",
    kategori: "Mesajlaşma",
    kitle: "",
    ton: "",
    yasaklar: "",
};

function kur(
    put: (govde: unknown) => Response = (g) => new Response(JSON.stringify(g)),
) {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    const istekler: {
        yontem: string;
        adres: string;
        jeton: string | null;
        govde: unknown;
    }[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const govde = istek.method === "PUT" ? await istek.json() : null;
            istekler.push({
                yontem: istek.method,
                adres: new URL(istek.url, "http://x").pathname,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde,
            });
            if (istek.method === "PUT") return put(govde);
            if (istek.url.endsWith("/panel/api/uygulamalar"))
                return new Response(
                    JSON.stringify({ uygulamalar: ["keepchat"] }),
                );
            return new Response(JSON.stringify(KAYITLI));
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/baglam"],
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

describe("Ürün bağlamı", () => {
    it("kayıtlıyı yükler; değişmeden Kaydet kapalı; menüde işaretli", async () => {
        kur();
        expect(await screen.findByDisplayValue("Sade mesajlaşma")).toBeTruthy();
        expect(
            (
                screen.getByRole("button", {
                    name: "Kaydet",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        expect(
            screen
                .getByRole("link", { name: "Ürün bağlamı" })
                .getAttribute("aria-current"),
        ).toBe("page");
        expect(screen.getByText("15/1000")).toBeTruthy();
    });

    it("düzenlenen bağlam kabuğun jetonuyla TAMAMI PUT edilir", async () => {
        const istekler = kur();
        fireEvent.change(await screen.findByLabelText("Hedef kitle"), {
            target: { value: "Aileler" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Kaydet" }));

        await screen.findByText(/Kaydedildi/);
        const put = istekler.find((i) => i.yontem === "PUT");
        expect(put).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/baglam",
            jeton: "jeton-kabuk",
            govde: { ...KAYITLI, kitle: "Aileler" },
        });
        // GET istekleri jeton taşımaz.
        expect(
            istekler
                .filter((i) => i.yontem === "GET")
                .every((i) => i.jeton === null),
        ).toBe(true);
    });

    it("sunucu reddederse nedenini gösterir", async () => {
        kur(
            () =>
                new Response(
                    JSON.stringify({ hatalar: ["kitle: en çok 500 karakter"] }),
                    { status: 422 },
                ),
        );
        fireEvent.change(await screen.findByLabelText("Hedef kitle"), {
            target: { value: "x" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Kaydet" }));
        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toBe(
                "kitle: en çok 500 karakter",
            ),
        );
    });
});
