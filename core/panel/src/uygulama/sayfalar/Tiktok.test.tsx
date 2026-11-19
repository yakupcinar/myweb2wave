// @vitest-environment happy-dom
// A31 (TB2): Entegrasyonlar › TikTok — Meta'nın (A30) karşılığı. Kayıtlı
// anahtar yalnız son 4 hanesiyle; boş anahtar alanı gövdede null (değişmez),
// "Kaldır" '' gönderir; kayıt kabuğun jetonuyla; dene kayıtlı değerlerle ve
// yalnız kaydedilmemiş değişiklik yokken; bağlantıyı kaldırmak onay ister.
// Sayfada Meta bölümünün de aynı adlı düğmeleri var — hepsi TikTok bölümünde
// aranır. Sunucu yerine sahte fetch.
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
    pixelKodu: "CABCDE12345",
    eventsAnahtari: { durum: "kayitli", son4: "6789" },
    testKodu: "",
    reklamveren: "7123456789012",
    raporAnahtari: { durum: "okunamadi", son4: null },
    sifrelemeHazir: true,
    guncellendi: "2026-11-17T10:00:00+00:00",
};

type Istek = {
    yontem: string;
    adres: string;
    jeton: string | null;
    govde: unknown;
};

function kur(tiktok: object = KAYITLI, yaz?: (i: Istek) => Response) {
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
                return yaz ? yaz(i) : new Response(JSON.stringify(tiktok));
            }
            if (yol.endsWith("/tiktok"))
                return new Response(JSON.stringify(tiktok));
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

const bolum = () => screen.findByRole("region", { name: "TikTok reklamları" });
const dugme = (ad: string) =>
    within(document.getElementById("tiktok")!).getByRole("button", {
        name: ad,
    }) as HTMLButtonElement;

afterEach(() => {
    cleanup();
    document.getElementById("panel-uygulama")?.remove();
    vi.unstubAllGlobals();
});

describe("TikTok bağlantısı", () => {
    it("kayıtlı değerler; anahtar yalnız son 4 hane; okunamayan anahtar işaretli; değişmeden Kaydet kapalı", async () => {
        kur();
        const b = within(await bolum());
        expect(await b.findByDisplayValue("CABCDE12345")).toBeTruthy();
        expect(b.getByDisplayValue("7123456789012")).toBeTruthy();
        const events = b.getByLabelText(
            "Events API anahtarı",
        ) as HTMLInputElement;
        expect(events.type).toBe("password");
        expect(events.value).toBe("");
        expect(events.placeholder).toBe(
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
        fireEvent.change(await b.findByDisplayValue("CABCDE12345"), {
            target: { value: "CZZZZZ99999" },
        });
        expect(dugme("Bağlantıyı dene").disabled).toBe(true);
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        await waitFor(() => expect(istekler).toHaveLength(1));
        expect(istekler[0]).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/tiktok",
            jeton: "jeton-kabuk",
            govde: {
                pixelKodu: "CZZZZZ99999",
                testKodu: "",
                reklamveren: "7123456789012",
                eventsAnahtari: null,
                raporAnahtari: null,
            },
        });

        cleanup();
        document.getElementById("panel-uygulama")?.remove();
        const ikinci = kur();
        const c = within(await bolum());
        fireEvent.change(await c.findByLabelText("Events API anahtarı"), {
            target: { value: " ttyeni_anahtar_0123456789 " },
        });
        const harcama = c.getByRole("region", { name: "Harcama" });
        fireEvent.click(
            within(harcama).getByRole("button", { name: "Kaldır" }),
        );
        expect(
            (c.getByLabelText("Marketing API anahtarı") as HTMLInputElement)
                .disabled,
        ).toBe(true);
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        await waitFor(() => expect(ikinci).toHaveLength(1));
        expect(ikinci[0]?.govde).toEqual({
            pixelKodu: "CABCDE12345",
            testKodu: "",
            reklamveren: "7123456789012",
            eventsAnahtari: "ttyeni_anahtar_0123456789",
            raporAnahtari: "",
        });
    });

    it("dene: kayıtlı değerlerle POST, sonuç parça parça", async () => {
        const istekler = kur(
            KAYITLI,
            () =>
                new Response(
                    JSON.stringify({
                        donusum: {
                            durum: "kayitli",
                            mesaj: "Denemek için test olay kodu girin",
                        },
                        harcama: {
                            durum: "hata",
                            mesaj: "TikTok reddetti: Access token is incorrect (kod 40105)",
                        },
                    }),
                ),
        );
        const b = within(await bolum());
        await b.findByDisplayValue("CABCDE12345");
        fireEvent.click(dugme("Bağlantıyı dene"));
        const sonuc = within(
            await b.findByRole("region", { name: "Deneme sonucu" }),
        );
        expect(
            sonuc.getAllByRole("listitem").map((l) => l.textContent),
        ).toEqual([
            "DönüşümKayıtlıDenemek için test olay kodu girin",
            "HarcamaHataTikTok reddetti: Access token is incorrect (kod 40105)",
        ]);
        expect(istekler.map((i) => [i.yontem, i.adres, i.jeton])).toEqual([
            [
                "POST",
                "/panel/api/uygulamalar/keepchat/tiktok/dene",
                "jeton-kabuk",
            ],
        ]);
    });

    it("bağlantıyı kaldır onay ister; Vazgeç istek göndermez", async () => {
        const istekler = kur();
        const b = within(await bolum());
        await b.findByDisplayValue("CABCDE12345");
        fireEvent.click(dugme("Bağlantıyı kaldır"));
        expect(
            b.getByText("Dönüşüm ve harcama durur. Emin misin?"),
        ).toBeTruthy();
        fireEvent.click(dugme("Vazgeç"));
        expect(istekler).toEqual([]);
        fireEvent.click(dugme("Bağlantıyı kaldır"));
        const onay = b.getByText(
            "Dönüşüm ve harcama durur. Emin misin?",
        ).parentElement!;
        fireEvent.click(within(onay).getByRole("button", { name: "Kaldır" }));
        await waitFor(() =>
            expect(istekler.map((i) => [i.yontem, i.adres])).toEqual([
                ["DELETE", "/panel/api/uygulamalar/keepchat/tiktok"],
            ]),
        );
    });

    it("bağlı değilken kaldır ve dene yok/kapalı; şifreleme anahtarı yoksa uyarı; sunucu hatası satırda", async () => {
        kur(
            {
                ...KAYITLI,
                pixelKodu: "",
                reklamveren: "",
                eventsAnahtari: { durum: "yok", son4: null },
                raporAnahtari: { durum: "yok", son4: null },
                sifrelemeHazir: false,
                guncellendi: null,
            },
            () =>
                new Response(
                    JSON.stringify({
                        hatalar: ["pixelKodu: büyük harf ve rakam"],
                    }),
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
        fireEvent.change(b.getByLabelText("Pixel kodu"), {
            target: { value: "cabc" },
        });
        fireEvent.click(dugme("Bağlantıyı kaydet"));
        expect((await b.findByRole("alert")).textContent).toBe(
            "pixelKodu: büyük harf ve rakam",
        );
    });
});
