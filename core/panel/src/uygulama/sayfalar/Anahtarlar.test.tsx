// @vitest-environment happy-dom
// A5a: API anahtarları sayfası — oluşturunca anahtar ve Claude Code komutu
// BİR KEZ görünür; iptal iki adımlı; yazma istekleri kabuğun jetonuyla.
// P7d (A25): Entegrasyonlar'da; iptal edilenler katlanır, oradan kalıcı silinir.
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
import { bosEntegrasyon } from "./entegrasyonSahte";

const ANAHTAR = `mw2w_${"a".repeat(48)}`;
const IPTAL = {
    id: 5,
    ad: "Eski dizüstü",
    onek: "mw2w_cccccc",
    olusturma: "2026-10-20T08:00:00+00:00",
    sonKullanim: null,
    iptal: true,
    yonetim: false,
};

function kur(ek: (typeof IPTAL)[] = []) {
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
        ...ek,
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
                const sil = adres.includes("/iptal-edilenler/");
                liste = sil
                    ? liste.filter((a) => !adres.endsWith(`/${a.id}`))
                    : liste.map((a) =>
                          adres.endsWith(`/${a.id}`)
                              ? { ...a, iptal: true }
                              : a,
                      );
                return new Response(null, { status: 204 });
            }
            if (adres.endsWith("/anahtarlar"))
                return j({
                    mcpAdresi: "https://cekirdek.test/mcp",
                    anahtarlar: liste,
                });
            return bosEntegrasyon(adres) ?? j({}, 404);
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/entegrasyonlar"],
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
        const bolum = within(
            screen.getByRole("region", { name: "API anahtarları" }),
        );
        fireEvent.click(bolum.getByRole("checkbox"));
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
        expect((bolum.getByRole("checkbox") as HTMLInputElement).checked).toBe(
            false,
        );
    });

    it("iptal iki adımlı; onaydan önce istek gitmez; sonra İptal edilenler'e geçer", async () => {
        const istekler = kur();
        const tablo = await screen.findByRole("region", { name: "Anahtarlar" });
        fireEvent.click(
            await within(tablo).findByRole("button", { name: "İptal et" }),
        );
        expect(istekler.some((i) => i.yontem === "DELETE")).toBe(false);

        fireEvent.click(
            within(tablo).getByRole("button", { name: "Evet, iptal et" }),
        );
        await within(tablo).findByText("Etkin anahtar yok.");
        expect(
            within(tablo).getByRole("button", { name: "İptal edilenler (1)" }),
        ).toBeTruthy();
        expect(istekler.find((i) => i.yontem === "DELETE")).toEqual({
            yontem: "DELETE",
            adres: "/panel/api/uygulamalar/keepchat/anahtarlar/7",
            jeton: "jeton-kabuk",
        });
    });

    it("P7d: iptal edilenler katlı; açılınca kalıcı silme iki adımlı, jetonla", async () => {
        const istekler = kur([IPTAL]);
        const tablo = await screen.findByRole("region", { name: "Anahtarlar" });
        await within(tablo).findByText("Eski");
        expect(within(tablo).queryByText("Eski dizüstü")).toBeNull();
        const ac = within(tablo).getByRole("button", {
            name: "İptal edilenler (1)",
        });
        expect(ac.getAttribute("aria-expanded")).toBe("false");
        fireEvent.click(ac);
        expect(ac.getAttribute("aria-expanded")).toBe("true");
        const iptaller = within(
            within(tablo).getByRole("table", {
                name: "İptal edilen anahtarlar",
            }),
        );
        expect(iptaller.getByText("Eski dizüstü")).toBeTruthy();
        fireEvent.click(iptaller.getByRole("button", { name: "Sil" }));
        expect(iptaller.getByText(/Kalıcı silinsin mi/)).toBeTruthy();
        expect(istekler.some((i) => i.yontem === "DELETE")).toBe(false);
        fireEvent.click(iptaller.getByRole("button", { name: "Evet, sil" }));
        await waitFor(() =>
            expect(
                within(tablo).queryByRole("button", {
                    name: /İptal edilenler/,
                }),
            ).toBeNull(),
        );
        expect(istekler.find((i) => i.yontem === "DELETE")).toEqual({
            yontem: "DELETE",
            adres: "/panel/api/uygulamalar/keepchat/anahtarlar/iptal-edilenler/5",
            jeton: "jeton-kabuk",
        });
        expect(within(tablo).getByText("Eski")).toBeTruthy();
    });
});
