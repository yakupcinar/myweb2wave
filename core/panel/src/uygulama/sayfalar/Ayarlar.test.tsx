// @vitest-environment happy-dom
// M9a (A23): ayarlar sayfası — yükler, düzenler, kabuğun CSRF jetonuyla PUT
// eder; sunucunun hatasını gösterir; menüde işaretli. Sunucu yerine sahte fetch.
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
    ad: "KeepChat",
    sema: "keepchat",
    magazaAdresi: "",
    destekEpostasi: "",
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
            if (istek.method === "DELETE")
                return new Response(
                    JSON.stringify({
                        kaynak: null,
                        alindi: null,
                        tema: false,
                        logo: false,
                        paywall: false,
                    }),
                );
            if (istek.url.endsWith("/varsayilanlar"))
                return new Response(
                    JSON.stringify({
                        kaynak: "genel",
                        alindi: "2026-11-10T10:00:00+00:00",
                        tema: true,
                        logo: false,
                        paywall: true,
                    }),
                );
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
                    initialEntries: ["/panel/ayarlar"],
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

describe("Ayarlar", () => {
    it("kayıtlıyı yükler; değişmeden Kaydet kapalı; menüde işaretli", async () => {
        kur();
        expect(await screen.findByDisplayValue("KeepChat")).toBeTruthy();
        expect(screen.getByDisplayValue("keepchat")).toBeTruthy();
        expect(
            (
                screen.getByRole("button", {
                    name: "Kaydet",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        expect(
            screen
                .getByRole("link", { name: "Ayarlar" })
                .getAttribute("aria-current"),
        ).toBe("page");
    });

    it("düzenlenen ayarlar kabuğun jetonuyla TAMAMI PUT edilir", async () => {
        const istekler = kur();
        fireEvent.change(await screen.findByLabelText("App Store adresi"), {
            target: { value: "https://apps.apple.com/app/id1" },
        });
        fireEvent.change(screen.getByLabelText("Destek e-postası"), {
            target: { value: "destek@ornek.com" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Kaydet" }));

        await screen.findByText("Kaydedildi.");
        const put = istekler.find((i) => i.yontem === "PUT");
        expect(put).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/ayarlar",
            jeton: "jeton-kabuk",
            govde: {
                ...KAYITLI,
                magazaAdresi: "https://apps.apple.com/app/id1",
                destekEpostasi: "destek@ornek.com",
            },
        });
        expect(
            istekler
                .filter((i) => i.yontem === "GET")
                .every((i) => i.jeton === null),
        ).toBe(true);
    });

    it("tarayıcı denetimi yerine sunucunun hatası gösterilir", async () => {
        // noValidate: geçersiz e-posta tarayıcıda sessizce takılmaz (§44
        // ailesi), sunucuya gider ve gerekçe satırda görünür.
        const istekler = kur(
            () =>
                new Response(
                    JSON.stringify({
                        hatalar: ["destekEpostasi: geçerli e-posta ya da boş"],
                    }),
                    { status: 422 },
                ),
        );
        fireEvent.change(await screen.findByLabelText("Destek e-postası"), {
            target: { value: "destek" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Kaydet" }));
        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toBe(
                "destekEpostasi: geçerli e-posta ya da boş",
            ),
        );
        expect(istekler.some((i) => i.yontem === "PUT")).toBe(true);
    });

    it("M9c: yeni huni görünümü kaynağı ve içeriği; Kaldır jetonla DELETE gönderir", async () => {
        const istekler = kur();
        expect(await screen.findByText("genel")).toBeTruthy();
        expect(screen.getByText(/tema, paywall\./)).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: "Kaldır" }));
        await screen.findByText(/Yok — yeni huniler boş başlar/);
        expect(istekler.find((i) => i.yontem === "DELETE")).toEqual({
            yontem: "DELETE",
            adres: "/panel/api/uygulamalar/keepchat/varsayilanlar",
            jeton: "jeton-kabuk",
            govde: null,
        });
    });
});
