// @vitest-environment happy-dom
// M9b1 (A23): yasal metinler sayfası — taslak uyarısı ve durumu, dil seçimi,
// "Kaydet ve yayınla" doğru belge/dil adresine kabuğun jetonuyla gider, yanıt
// sayfayı yayında gösterir; veriler TAMAMI gider; sunucu hatası satırda.
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

const belge = (dil: "tr" | "en", ek: object = {}) => ({
    belge: "sartlar",
    dil,
    baslik: dil === "tr" ? "Kullanım şartları" : "Terms of use",
    metin: dil === "tr" ? "TR taslak {{unvan}}" : "EN draft",
    kayitli: false,
    guncellendi: null,
    eksikler: dil === "tr" ? ["unvan"] : [],
    adres: `http://localhost/yasal/keepchat/sartlar/${dil}`,
    ...ek,
});
const YANIT = {
    veriler: { unvan: "", adres: "", kayitNo: "" },
    belgeler: [belge("tr"), belge("en")],
};

function kur(put?: (adres: string, govde: unknown) => Response) {
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
            const adres = new URL(istek.url, "http://x").pathname;
            istekler.push({
                yontem: istek.method,
                adres,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde,
            });
            if (istek.method === "PUT")
                return put
                    ? put(adres, govde)
                    : new Response(
                          JSON.stringify({
                              ...YANIT,
                              belgeler: [
                                  belge("tr", {
                                      kayitli: true,
                                      guncellendi: "2026-11-10T10:00:00+00:00",
                                      metin: (govde as { metin: string }).metin,
                                  }),
                                  belge("en"),
                              ],
                          }),
                      );
            if (istek.url.endsWith("/panel/api/uygulamalar"))
                return new Response(
                    JSON.stringify({ uygulamalar: ["keepchat"] }),
                );
            return new Response(JSON.stringify(YANIT));
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/yasal"],
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

describe("Yasal metinler", () => {
    it("taslak uyarısı, taslak durumu, eksik yer tutucu; menüde işaretli", async () => {
        kur();
        expect(
            await screen.findByDisplayValue("TR taslak {{unvan}}"),
        ).toBeTruthy();
        expect(screen.getByRole("note").textContent).toContain(
            "hukuki görüş değildir",
        );
        expect(screen.getByText("Taslak — yayında değil")).toBeTruthy();
        expect(screen.getByText(/Boş değer sayfada/).textContent).toContain(
            "{{unvan}}",
        );
        expect(
            screen
                .getByRole("link", { name: "Yasal metinler" })
                .getAttribute("aria-current"),
        ).toBe("page");
    });

    it("dil seçilince o dilin metni; kaydet doğru adrese jetonla gider ve yayında gösterir", async () => {
        const istekler = kur();
        await screen.findByDisplayValue("TR taslak {{unvan}}");
        const grup = screen.getByRole("group", { name: "Dil" });
        fireEvent.click(
            within(grup).getByRole("button", { name: "İngilizce" }),
        );
        expect(screen.getByDisplayValue("EN draft")).toBeTruthy();
        expect(
            within(grup)
                .getByRole("button", { name: "İngilizce" })
                .getAttribute("aria-pressed"),
        ).toBe("true");
        fireEvent.click(within(grup).getByRole("button", { name: "Türkçe" }));

        fireEvent.change(screen.getByLabelText("Kullanım şartları (Türkçe)"), {
            target: { value: "Yeni metin" },
        });
        fireEvent.click(
            screen.getByRole("button", { name: "Kaydet ve yayınla" }),
        );

        await screen.findByText("Yayında");
        expect(
            screen.getByRole("link", {
                name: "http://localhost/yasal/keepchat/sartlar/tr",
            }),
        ).toBeTruthy();
        expect(istekler.find((i) => i.yontem === "PUT")).toEqual({
            yontem: "PUT",
            adres: "/panel/api/uygulamalar/keepchat/yasal/sartlar/tr",
            jeton: "jeton-kabuk",
            govde: { metin: "Yeni metin" },
        });

        // İngilizce kendi adresine gider.
        fireEvent.click(
            within(screen.getByRole("group", { name: "Dil" })).getByRole(
                "button",
                { name: "İngilizce" },
            ),
        );
        fireEvent.click(
            screen.getByRole("button", { name: "Kaydet ve yayınla" }),
        );
        await waitFor(() =>
            expect(
                istekler.filter((i) => i.yontem === "PUT").map((i) => i.adres),
            ).toEqual([
                "/panel/api/uygulamalar/keepchat/yasal/sartlar/tr",
                "/panel/api/uygulamalar/keepchat/yasal/sartlar/en",
            ]),
        );
    });

    it("yasal veriler TAMAMI gider; sunucu hatası satırda", async () => {
        const istekler = kur(
            () =>
                new Response(
                    JSON.stringify({ hatalar: ["unvan: en çok 200 karakter"] }),
                    { status: 422 },
                ),
        );
        fireEvent.change(await screen.findByLabelText("Şirket unvanı"), {
            target: { value: "Örnek A.Ş." },
        });
        fireEvent.click(screen.getByRole("button", { name: "Kaydet" }));
        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toBe(
                "unvan: en çok 200 karakter",
            ),
        );
        expect(istekler.find((i) => i.yontem === "PUT")?.govde).toEqual({
            unvan: "Örnek A.Ş.",
            adres: "",
            kayitNo: "",
        });
        expect(istekler.find((i) => i.yontem === "PUT")?.adres).toBe(
            "/panel/api/uygulamalar/keepchat/yasal/veriler",
        );
    });
});
