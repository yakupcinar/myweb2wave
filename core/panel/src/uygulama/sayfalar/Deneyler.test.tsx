// @vitest-environment happy-dom
// M4a (A17): Deneyler sayfası — yeni taslak payları 100'e tamamlar ve
// yönlendirmede ilk varyantın hunisi kontrol hunisidir; durum düğmeleri
// geçişlere uyar (taslak yalnız başlar, bitmiş deneyde düğme yok); çekirdeğin
// Türkçe hatası görünür. İstekler kabuğun jetonuyla.
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

const deney = (ad: string, durum: string) => ({
    ad,
    tur: "icerik",
    huni: "genel",
    varyantlar: [
        { ad: "a", pay: 50 },
        { ad: "b", pay: 50 },
    ],
    durum,
    baslangic: null,
    bitis: null,
});

function kur(yanit422?: string) {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    let deneyler: unknown[] = [
        deney("taslakta", "taslak"),
        deney("calisan", "aktif"),
        deney("biten", "bitti"),
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
            // M4b: sonuçlar — calisan ve biten deneyler için.
            if (adres.endsWith("/sonuclar"))
                return j({
                    ad: adres.split("/").at(-2),
                    durum: "aktif",
                    varyantlar: [
                        {
                            ad: "a",
                            ziyaret: 200,
                            odemeyeGecti: 40,
                            abone: 20,
                            odedi: 12,
                            kazanmaOlasiligi: 0.0917,
                        },
                        {
                            ad: "b",
                            ziyaret: 0,
                            odemeyeGecti: 0,
                            abone: 0,
                            odedi: 0,
                            kazanmaOlasiligi: 0.9083,
                        },
                    ],
                });
            if (yanit422 && istek.method !== "GET")
                return j({ hatalar: [yanit422] }, 422);
            if (istek.method === "POST") {
                deneyler = [deney("yeni", "taslak"), ...deneyler];
                return j({ deneyler }, 201);
            }
            if (istek.method === "PUT") {
                deneyler = deneyler.map((d, i) =>
                    i === 0 ? { ...(d as object), durum: "aktif" } : d,
                );
                return j({ deneyler });
            }
            return j({ deneyler });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/deneyler"],
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

const satir = async (ad: string) =>
    within((await screen.findByText(ad)).closest("div")!);

describe("Deneyler", () => {
    it("M4b: taslak dışı deneyde sonuç tablosu; dönüşüm abone/ziyaret, olasılık yüzde", async () => {
        const istekler = kur();
        const tablo = within(
            await screen.findByRole("table", { name: "calisan sonuçları" }),
        );
        const satirlar = tablo
            .getAllByRole("row")
            .map((r) =>
                [...r.querySelectorAll("th,td")].map((h) => h.textContent),
            );
        expect(satirlar).toEqual([
            [
                "Varyant",
                "Ziyaret",
                "Ödemeye geçti",
                "Abone oldu",
                "Dönüşüm",
                "Ödedi",
                "Kazanma olasılığı",
            ],
            ["a", "200", "40", "20", "10,0 %", "12", "9,2 %"],
            ["b", "0", "0", "0", "—", "0", "90,8 %"],
        ]);
        expect(
            await screen.findByRole("table", { name: "biten sonuçları" }),
        ).toBeTruthy();
        expect(
            screen.queryByRole("table", { name: "taslakta sonuçları" }),
        ).toBeNull();
        expect(
            istekler
                .filter((i) => i.adres.endsWith("/sonuclar"))
                .map((i) => i.adres)
                .sort(),
        ).toEqual([
            "/panel/api/uygulamalar/keepchat/deneyler/biten/sonuclar",
            "/panel/api/uygulamalar/keepchat/deneyler/calisan/sonuclar",
        ]);
    });

    it("durum düğmeleri geçişlere uyar", async () => {
        kur();
        const taslak = await satir("taslakta");
        expect(taslak.getAllByRole("button").map((b) => b.textContent)).toEqual(
            ["Başlat"],
        );
        const calisan = await satir("calisan");
        expect(
            calisan.getAllByRole("button").map((b) => b.textContent),
        ).toEqual(["Durdur", "Bitir"]);
        expect((await satir("biten")).queryAllByRole("button")).toHaveLength(0);
    });

    it("Başlat jetonla PUT durum=aktif gönderir, liste yanıttan", async () => {
        const istekler = kur();
        fireEvent.click(
            (await satir("taslakta")).getByRole("button", { name: "Başlat" }),
        );
        await waitFor(() =>
            expect(istekler.some((i) => i.yontem === "PUT")).toBe(true),
        );
        expect(istekler.find((i) => i.yontem === "PUT")).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/deneyler/taslakta/durum",
            jeton: "jeton-kabuk",
            govde: { durum: "aktif" },
        });
        expect(
            await (
                await satir("taslakta")
            ).findByRole("button", { name: "Durdur" }),
        ).toBeTruthy();
    });

    it("yönlendirme taslağı: paylar 100'e tamamlanır, ilk varyant kontrol hunisi", async () => {
        const istekler = kur();
        const form = within(
            await screen.findByRole("form", { name: "Yeni deney" }),
        );
        fireEvent.change(form.getByLabelText("Deney adı"), {
            target: { value: "acilis" },
        });
        fireEvent.change(form.getByLabelText("Tür"), {
            target: { value: "yonlendirme" },
        });
        fireEvent.change(form.getByLabelText("Kontrol hunisi"), {
            target: { value: "genel" },
        });
        fireEvent.change(form.getByLabelText("Kontrol payı (%)"), {
            target: { value: "70" },
        });
        fireEvent.change(form.getByLabelText("Varyant hunisi"), {
            target: { value: "yeni-huni" },
        });
        fireEvent.click(form.getByRole("button", { name: "Taslağı kaydet" }));
        await waitFor(() =>
            expect(istekler.some((i) => i.yontem === "POST")).toBe(true),
        );
        const post = istekler.find((i) => i.yontem === "POST");
        expect(post?.jeton).toBe("jeton-kabuk");
        expect(post?.govde).toEqual({
            ad: "acilis",
            tur: "yonlendirme",
            huni: "genel",
            varyantlar: [
                { ad: "kontrol", pay: 70, huni: "genel" },
                { ad: "varyant", pay: 30, huni: "yeni-huni" },
            ],
        });
        expect(await screen.findByText("yeni")).toBeTruthy();
    });

    it("içerik taslağında varyantta huni alanı yok", async () => {
        const istekler = kur();
        const form = within(
            await screen.findByRole("form", { name: "Yeni deney" }),
        );
        expect(form.queryByLabelText("Varyant hunisi")).toBeNull();
        fireEvent.change(form.getByLabelText("Deney adı"), {
            target: { value: "baslik" },
        });
        fireEvent.change(form.getByLabelText("Huni"), {
            target: { value: "genel" },
        });
        fireEvent.click(form.getByRole("button", { name: "Taslağı kaydet" }));
        await waitFor(() =>
            expect(istekler.some((i) => i.yontem === "POST")).toBe(true),
        );
        expect(
            (
                istekler.find((i) => i.yontem === "POST")?.govde as {
                    varyantlar: unknown;
                }
            ).varyantlar,
        ).toEqual([
            { ad: "kontrol", pay: 50 },
            { ad: "varyant", pay: 50 },
        ]);
    });

    it("çekirdeğin hatası görünür", async () => {
        kur("'genel' hunisi yayında değil — önce yayınla.");
        fireEvent.click(
            (await satir("taslakta")).getByRole("button", { name: "Başlat" }),
        );
        expect((await screen.findByRole("alert")).textContent).toBe(
            "'genel' hunisi yayında değil — önce yayınla.",
        );
    });
});
