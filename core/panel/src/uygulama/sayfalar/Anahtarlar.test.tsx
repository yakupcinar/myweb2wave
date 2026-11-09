// @vitest-environment happy-dom
// A5a: API anahtarları sayfası — oluşturunca anahtar ve Claude Code komutu
// BİR KEZ görünür; iptal iki adımlı; yazma istekleri kabuğun jetonuyla.
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

const ANAHTAR = `mw2w_${"a".repeat(48)}`;

function kur() {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    let liste = [
        {
            id: 7,
            ad: "Eski",
            onek: "mw2w_bbbbbb",
            olusturma: "2026-10-27T08:00:00+00:00",
            sonKullanim: null,
            iptal: false,
            yonetim: false,
        },
    ];
    govdeler = [];
    const istekler: { yontem: string; adres: string; jeton: string | null }[] =
        [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const adres = new URL(istek.url, "http://x").pathname;
            istekler.push({
                yontem: istek.method,
                adres,
                jeton: istek.headers.get("X-CSRF-Token"),
            });
            const j = (o: unknown, durum = 200) =>
                new Response(JSON.stringify(o), { status: durum });
            if (adres === "/panel/api/uygulamalar")
                return j({ uygulamalar: ["keepchat"] });
            if (istek.method === "POST") {
                const govde = (await istek.json()) as {
                    ad: string;
                    yonetim: boolean;
                };
                govdeler.push(govde);
                const { ad } = govde;
                liste = [
                    {
                        id: 8,
                        ad,
                        onek: ANAHTAR.slice(0, 11),
                        olusturma: "2026-10-27T09:00:00+00:00",
                        sonKullanim: null,
                        iptal: false,
                        yonetim: govde.yonetim,
                    },
                    ...liste,
                ];
                return j(
                    { id: 8, ad, anahtar: ANAHTAR, yonetim: govde.yonetim },
                    201,
                );
            }
            if (istek.method === "DELETE") {
                liste = liste.map((a) =>
                    adres.endsWith(`/${a.id}`) ? { ...a, iptal: true } : a,
                );
                return new Response(null, { status: 204 });
            }
            return j({
                mcpAdresi: "https://cekirdek.test/mcp",
                anahtarlar: liste,
            });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/anahtarlar"],
                })}
            />
        </QueryClientProvider>,
    );
    return istekler;
}

let govdeler: { ad: string; yonetim: boolean }[] = [];

afterEach(() => {
    cleanup();
    document.getElementById("panel-uygulama")?.remove();
    vi.unstubAllGlobals();
});

describe("API anahtarları", () => {
    it("oluşturunca anahtar ve hazır Claude Code komutu bir kez görünür; kapatınca bir daha yok", async () => {
        const istekler = kur();
        fireEvent.change(await screen.findByLabelText("Ad"), {
            target: { value: "Claude Code" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Oluştur" }));

        const kart = await screen.findByRole("region", {
            name: "Yeni anahtar",
        });
        expect(within(kart).getByText(ANAHTAR)).toBeTruthy();
        expect(
            within(kart).getByText(
                `claude mcp add --transport http myweb2wave https://cekirdek.test/mcp --header "Authorization: Bearer ${ANAHTAR}"`,
            ),
        ).toBeTruthy();
        expect(istekler.find((i) => i.yontem === "POST")?.jeton).toBe(
            "jeton-kabuk",
        );
        await waitFor(() =>
            expect(screen.getAllByText(/mw2w_aaaaaa…/).length).toBe(1),
        );

        fireEvent.click(
            within(kart).getByRole("button", { name: "Kaydettim, kapat" }),
        );
        expect(
            screen.queryByRole("region", { name: "Yeni anahtar" }),
        ).toBeNull();
        expect(document.body.textContent).not.toContain(ANAHTAR);
    });

    it("M7c2: yönetim izni varsayılan kapalı; işaretlenince gövdede ve listede rozet", async () => {
        kur();
        fireEvent.change(await screen.findByLabelText("Ad"), {
            target: { value: "Okur" },
        });
        fireEvent.click(screen.getByRole("button", { name: "Oluştur" }));
        await waitFor(() =>
            expect(govdeler).toEqual([{ ad: "Okur", yonetim: false }]),
        );
        await screen.findByRole("region", { name: "Yeni anahtar" });
        fireEvent.click(
            screen.getByRole("button", { name: "Kaydettim, kapat" }),
        );

        fireEvent.change(screen.getByLabelText("Ad"), {
            target: { value: "Yönetici" },
        });
        fireEvent.click(screen.getByRole("checkbox"));
        fireEvent.click(screen.getByRole("button", { name: "Oluştur" }));
        await waitFor(() =>
            expect(govdeler.at(-1)).toEqual({ ad: "Yönetici", yonetim: true }),
        );
        const tablo = await screen.findByRole("region", { name: "Anahtarlar" });
        await waitFor(() =>
            expect(
                within(tablo).getByText("Yönetici").closest("tr")?.textContent,
            ).toContain("yönetim"),
        );
        expect(
            within(tablo).getByText("Okur").closest("tr")?.textContent,
        ).not.toContain("yönetim");
        expect((screen.getByRole("checkbox") as HTMLInputElement).checked).toBe(
            false,
        );
    });

    it("iptal iki adımlı; onaydan önce istek gitmez; sonra durum iptal", async () => {
        const istekler = kur();
        const tablo = await screen.findByRole("region", { name: "Anahtarlar" });
        fireEvent.click(
            await within(tablo).findByRole("button", { name: "İptal et" }),
        );
        expect(istekler.some((i) => i.yontem === "DELETE")).toBe(false);

        fireEvent.click(
            within(tablo).getByRole("button", { name: "Evet, iptal et" }),
        );
        await within(tablo).findByText("iptal");
        expect(istekler.find((i) => i.yontem === "DELETE")).toEqual({
            yontem: "DELETE",
            adres: "/panel/api/uygulamalar/keepchat/anahtarlar/7",
            jeton: "jeton-kabuk",
        });
    });
});
