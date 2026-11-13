// @vitest-environment happy-dom
// A30 (MB2): Entegrasyonlar › Meta — kayıtlı anahtar yalnız son 4 hanesiyle;
// boş anahtar alanı gövdede null (değişmez), "Kaldır" '' gönderir; kayıt
// kabuğun jetonuyla; dene kayıtlı değerlerle ve yalnız kaydedilmemiş değişiklik
// yokken; bağlantıyı kaldırmak onay ister. Sunucu yerine sahte fetch.
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
import { bosEntegrasyon } from "./entegrasyonSahte";

const KAYITLI = {
    pixelId: "987654321",
    capiAnahtari: { durum: "kayitli", son4: "6789" },
    testKodu: "",
    reklamHesabi: "act_12345678",
    insightsAnahtari: { durum: "okunamadi", son4: null },
    sifrelemeHazir: true,
    surum: "v24.0",
    guncellendi: "2026-11-14T10:00:00+00:00",
};

type Istek = {
    yontem: string;
    adres: string;
    jeton: string | null;
    govde: unknown;
};

function kur(meta: object = KAYITLI, yaz?: (i: Istek) => Response) {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    const istekler: Istek[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const yol = new URL(istek.url, "http://x").pathname;
            const i: Istek = {
                yontem: istek.method,
                adres: yol,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde: istek.method === "PUT" ? await istek.json() : null,
            };
            if (istek.method !== "GET") {
                istekler.push(i);
                return yaz ? yaz(i) : new Response(JSON.stringify(meta));
            }
            if (yol.endsWith("/meta"))
                return new Response(JSON.stringify(meta));
            return bosEntegrasyon(yol) ?? new Response("{}", { status: 404 });
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

const bolum = () =>
    screen.findByRole("region", {
        name: "Meta (Facebook ve Instagram reklamları)",
    });
const dugme = (ad: string) =>
    screen.getByRole("button", { name: ad }) as HTMLButtonElement;

afterEach(() => {
    cleanup();
    document.getElementById("panel-uygulama")?.remove();
    vi.unstubAllGlobals();
});

describe("Meta bağlantısı", () => {
    it("kayıtlı değerler; anahtar yalnız son 4 hane; okunamayan anahtar işaretli; değişmeden Kaydet kapalı", async () => {
        kur();
        const b = within(await bolum());
        expect(await b.findByDisplayValue("987654321")).toBeTruthy();
        expect(b.getByDisplayValue("act_12345678")).toBeTruthy();
        const capi = b.getByLabelText(
            "Conversions API anahtarı",
        ) as HTMLInputElement;
        expect(capi.type).toBe("password");
        expect(capi.value).toBe("");
        expect(capi.placeholder).toBe(
            "Kayıtlı (…6789) — değiştirmek için yenisini yapıştır",
        );
        expect(b.getByText("Okunamadı — yeniden girin")).toBeTruthy();
        expect(dugme("Bağlantıyı kaydet").disabled).toBe(true);
        expect(dugme("Bağlantıyı dene").disabled).toBe(false);
        expect(ciplakAlanlar(document.body)).toEqual([]);
    });

    it("kaydet: boş anahtar null (değişmez), yeni anahtar değeri, Kaldır '' — jetonla", async () => {
        const istekler = kur();
        const b = within(await bolum());
        fireEvent.change(await b.findByDisplayValue("987654321"), {
            target: { value: "111112222" },
        });
        expect(dugme("Bağlantıyı dene").disabled).toBe(true);
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        await waitFor(() => expect(istekler).toHaveLength(1));
        expect(istekler[0]).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/meta",
            jeton: "jeton-kabuk",
            govde: {
                pixelId: "111112222",
                testKodu: "",
                reklamHesabi: "act_12345678",
                capiAnahtari: null,
                insightsAnahtari: null,
            },
        });

        cleanup();
        document.getElementById("panel-uygulama")?.remove();
        const ikinci = kur();
        const c = within(await bolum());
        fireEvent.change(await c.findByLabelText("Conversions API anahtarı"), {
            target: { value: " EAAGyeni_anahtar_0123456789 " },
        });
        const insights = c.getByRole("region", { name: "Harcama" });
        fireEvent.click(
            within(insights).getByRole("button", { name: "Kaldır" }),
        );
        expect(
            (c.getByLabelText("Insights anahtarı") as HTMLInputElement)
                .disabled,
        ).toBe(true);
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        await waitFor(() => expect(ikinci).toHaveLength(1));
        expect(ikinci[0]?.govde).toEqual({
            pixelId: "987654321",
            testKodu: "",
            reklamHesabi: "act_12345678",
            capiAnahtari: "EAAGyeni_anahtar_0123456789",
            insightsAnahtari: "",
        });
    });

    it("dene: kayıtlı değerlerle POST, sonuç parça parça", async () => {
        const istekler = kur(
            KAYITLI,
            () =>
                new Response(
                    JSON.stringify({
                        donusum: {
                            durum: "tamam",
                            mesaj: "Pixel: KeepChat Pixel",
                        },
                        harcama: {
                            durum: "hata",
                            mesaj: "Meta reddetti: Invalid OAuth access token",
                        },
                    }),
                ),
        );
        await within(await bolum()).findByDisplayValue("987654321");
        fireEvent.click(dugme("Bağlantıyı dene"));
        const sonuc = within(
            await screen.findByRole("region", { name: "Deneme sonucu" }),
        );
        expect(
            sonuc.getAllByRole("listitem").map((l) => l.textContent),
        ).toEqual([
            "DönüşümBağlıPixel: KeepChat Pixel",
            "HarcamaHataMeta reddetti: Invalid OAuth access token",
        ]);
        expect(istekler.map((i) => [i.yontem, i.adres, i.jeton])).toEqual([
            [
                "POST",
                "/panel/api/uygulamalar/keepchat/meta/dene",
                "jeton-kabuk",
            ],
        ]);
    });

    it("bağlantıyı kaldır onay ister; Vazgeç istek göndermez", async () => {
        const istekler = kur();
        await within(await bolum()).findByDisplayValue("987654321");
        fireEvent.click(dugme("Bağlantıyı kaldır"));
        expect(
            screen.getByText("Dönüşüm ve harcama durur. Emin misin?"),
        ).toBeTruthy();
        fireEvent.click(dugme("Vazgeç"));
        expect(istekler).toEqual([]);
        fireEvent.click(dugme("Bağlantıyı kaldır"));
        const onay = screen.getByText(
            "Dönüşüm ve harcama durur. Emin misin?",
        ).parentElement!;
        fireEvent.click(within(onay).getByRole("button", { name: "Kaldır" }));
        await waitFor(() =>
            expect(istekler.map((i) => [i.yontem, i.adres])).toEqual([
                ["DELETE", "/panel/api/uygulamalar/keepchat/meta"],
            ]),
        );
    });

    it("bağlı değilken kaldır ve dene yok/kapalı; şifreleme anahtarı yoksa uyarı; sunucu hatası satırda", async () => {
        kur(
            {
                ...KAYITLI,
                pixelId: "",
                reklamHesabi: "",
                capiAnahtari: { durum: "yok", son4: null },
                insightsAnahtari: { durum: "yok", son4: null },
                sifrelemeHazir: false,
                guncellendi: null,
            },
            () =>
                new Response(
                    JSON.stringify({ hatalar: ["pixelId: yalnız rakam"] }),
                    { status: 422 },
                ),
        );
        const b = within(await bolum());
        expect(await b.findByText(/şifreleme anahtarı yok/)).toBeTruthy();
        expect(
            b.queryByRole("button", { name: "Bağlantıyı kaldır" }),
        ).toBeNull();
        expect(b.queryByRole("button", { name: "Kaldır" })).toBeNull();
        expect(dugme("Bağlantıyı dene").disabled).toBe(true);
        fireEvent.change(b.getByLabelText("Pixel ID"), {
            target: { value: "12ab" },
        });
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        expect((await b.findByRole("alert")).textContent).toBe(
            "pixelId: yalnız rakam",
        );
    });
});
