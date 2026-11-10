// @vitest-environment happy-dom
// P2: Huniler sayfası — liste, "Aç", varsayılan, iki adımlı kaldırma (Sil /
// Arşivle), yeni huni → düzenleyici, kural ekle/taşı/sil; yazma istekleri
// kabuğun jetonuyla.
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

let yonlendirici: ReturnType<typeof createMemoryRouter>;
const konum = (): string => yonlendirici.state.location.pathname;

function kur() {
    const kabuk = document.createElement("div");
    kabuk.id = "panel-uygulama";
    kabuk.dataset["jeton"] = "jeton-kabuk";
    document.body.append(kabuk);
    const huni = (ad: string, ek: object = {}) => ({
        ad,
        varsayilan: false,
        yayinda: null,
        farkli: true,
        ziyaretli: false,
        ac: `https://huni.test/keepchat/?huni=${ad}&onizleme=x`,
        duzenle: `/panel/huniler/keepchat/${ad}`,
        ...ek,
    });
    const liste = {
        adres: "https://huni.test/keepchat/",
        parametreler: ["k", "utm_campaign"],
        huniler: [
            huni("genel", {
                varsayilan: true,
                yayinda: 3,
                farkli: false,
                ac: "https://huni.test/keepchat/?huni=genel&yayin=1&onizleme=y",
            }),
            huni("bos"),
            huni("eski", { ziyaretli: true, yayinda: 1 }),
        ],
        kurallar: [
            { id: 4, sira: 1, parametre: "k", deger: "fit", huni: "bos" },
            {
                id: 5,
                sira: 2,
                parametre: "utm_campaign",
                deger: "yaz",
                huni: "eski",
            },
        ],
        sablonlar: [
            { anahtar: "uyku", ad: "Uyku", aciklama: "Uyku düzeni testi" },
        ],
    };
    const istekler: Istek[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            const adres = new URL(istek.url, "http://x").pathname;
            const metin = await istek.text();
            istekler.push({
                yontem: istek.method,
                adres,
                jeton: istek.headers.get("X-CSRF-Token"),
                govde: metin ? JSON.parse(metin) : null,
            });
            const j = (o: unknown, durum = 200) =>
                new Response(JSON.stringify(o), { status: durum });
            if (adres === "/panel/api/uygulamalar")
                return j({ uygulamalar: ["keepchat"] });
            // Düzenleyici sayfası burada ölçülmüyor (HuniDuzenle.test).
            if (adres.endsWith("/duzenleyici"))
                return j({ hatalar: ["yok"] }, 404);
            // M9c: görünümü yeni hunilere kopyalanan huni.
            if (adres.endsWith("/varsayilanlar"))
                return j({
                    kaynak: "bos",
                    alindi: "2026-11-10T10:00:00+00:00",
                    tema: true,
                    logo: false,
                    paywall: true,
                });
            if (istek.method === "GET") return j(liste);
            if (adres.endsWith("/huniler") && istek.method === "POST") {
                const { ad } = JSON.parse(metin) as { ad: string };
                return ad === "genel"
                    ? j({ hatalar: ["'genel' hunisi zaten var"] }, 422)
                    : j({ ad, duzenle: `/panel/huniler/keepchat/${ad}` }, 201);
            }
            if (istek.method === "DELETE" && adres.includes("/huniler/"))
                return j({
                    sonuc: adres.endsWith("/eski") ? "arsivlendi" : "silindi",
                });
            return new Response(null, { status: 204 });
        }),
    );
    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider
                router={
                    (yonlendirici = createMemoryRouter(yollar, {
                        initialEntries: ["/panel/huniler"],
                    }))
                }
            />
        </QueryClientProvider>,
    );
    return istekler;
}

const satir = (ad: string): HTMLElement =>
    document.querySelector<HTMLElement>(`tr[data-huni="${ad}"]`)!;
const yazmalar = (istekler: Istek[]) =>
    istekler.filter((i) => i.yontem !== "GET");

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = "";
});

describe("P2: Huniler sayfası", () => {
    it("huniler: sürüm, taslak durumu, Düzenle ve süreli Aç; varsayılanda kaldırma yok", async () => {
        kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        expect(
            screen
                .getByRole("link", { name: "Huniler" })
                .getAttribute("aria-current"),
        ).toBe("page");
        const genel = within(satir("genel"));
        expect(genel.getByText("varsayılan")).toBeTruthy();
        expect(genel.getByText("v3")).toBeTruthy();
        expect(genel.getByText("yayındakiyle aynı")).toBeTruthy();
        expect(genel.queryByRole("button", { name: "Sil" })).toBeNull();
        expect(
            genel.queryByRole("button", { name: "Varsayılan yap" }),
        ).toBeNull();
        const ac = genel.getByRole("link", { name: "Aç" });
        expect(ac.getAttribute("href")).toBe(
            "https://huni.test/keepchat/?huni=genel&yayin=1&onizleme=y",
        );
        expect(ac.getAttribute("target")).toBe("_blank");
        expect(
            genel.getByRole("link", { name: "Düzenle" }).getAttribute("href"),
        ).toBe("/panel/huniler/keepchat/genel");
        expect(
            within(satir("bos")).getByText("yayınlanmamış değişiklik"),
        ).toBeTruthy();
        expect(within(satir("bos")).getByText("—")).toBeTruthy();
        // Ç41: ziyaretsiz "Sil", ziyaretli "Arşivle".
        expect(
            within(satir("bos")).getByRole("button", { name: "Sil" }),
        ).toBeTruthy();
        expect(
            within(satir("eski")).getByRole("button", { name: "Arşivle" }),
        ).toBeTruthy();
    });

    it("Düzenle düzenleyiciyi aynı uygulamada açar (P3)", async () => {
        kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        fireEvent.click(
            within(satir("bos")).getByRole("link", { name: "Düzenle" }),
        );
        await waitFor(() =>
            expect(konum()).toBe("/panel/huniler/keepchat/bos"),
        );
    });

    it("kaldırma iki adımlı; onaylanınca DELETE kabuğun jetonuyla, sonuç bildirilir", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        fireEvent.click(
            within(satir("eski")).getByRole("button", { name: "Arşivle" }),
        );
        expect(yazmalar(istekler)).toEqual([]);
        fireEvent.click(
            within(satir("eski")).getByRole("button", { name: "Vazgeç" }),
        );
        expect(
            within(satir("eski")).getByRole("button", { name: "Arşivle" }),
        ).toBeTruthy();
        fireEvent.click(
            within(satir("eski")).getByRole("button", { name: "Arşivle" }),
        );
        fireEvent.click(
            within(satir("eski")).getByRole("button", {
                name: "Evet, arşivle",
            }),
        );
        await screen.findByText(/'eski' arşivlendi/);
        expect(yazmalar(istekler)).toEqual([
            {
                yontem: "DELETE",
                adres: "/panel/api/uygulamalar/keepchat/huniler/eski",
                jeton: "jeton-kabuk",
                govde: null,
            },
        ]);
    });

    it("M9c: yeni huni örneği rozeti yalnız kaynakta; 'Yeni hunilere örnek yap' jetonla gider", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(satir("bos").textContent).toContain("yeni huni örneği"),
        );
        expect(satir("genel").textContent).not.toContain("yeni huni örneği");

        fireEvent.click(
            within(satir("genel")).getByRole("button", {
                name: "Yeni hunilere örnek yap",
            }),
        );
        await screen.findByText(/Yeni huniler artık 'genel' görünümüyle/);
        expect(yazmalar(istekler)).toEqual([
            {
                yontem: "POST",
                adres: "/panel/api/uygulamalar/keepchat/huniler/genel/ornek",
                jeton: "jeton-kabuk",
                govde: null,
            },
        ]);
    });

    it("varsayılan yap", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        fireEvent.click(
            within(satir("bos")).getByRole("button", {
                name: "Varsayılan yap",
            }),
        );
        await screen.findByText("Varsayılan huni artık 'bos'.");
        expect(yazmalar(istekler)).toEqual([
            {
                yontem: "POST",
                adres: "/panel/api/uygulamalar/keepchat/huniler/bos/varsayilan",
                jeton: "jeton-kabuk",
                govde: null,
            },
        ]);
    });

    it("yeni huni kopyadan açılır ve düzenleyiciye sayfa yenilemeden gidilir", async () => {
        // P3: düzenleyici aynı uygulamada açılır (sayfa yenilemeden).
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        const form = within(screen.getByRole("form", { name: "Yeni huni" }));
        fireEvent.change(form.getByLabelText("Yeni huni adı"), {
            target: { value: "fitness" },
        });
        fireEvent.change(form.getByLabelText("Başlangıç"), {
            target: { value: "huni:genel" },
        });
        fireEvent.click(form.getByRole("button", { name: "Huni aç" }));
        await waitFor(() =>
            expect(konum()).toBe("/panel/huniler/keepchat/fitness"),
        );
        expect(yazmalar(istekler)).toEqual([
            {
                yontem: "POST",
                adres: "/panel/api/uygulamalar/keepchat/huniler",
                jeton: "jeton-kabuk",
                govde: { ad: "fitness", kaynak: "genel" },
            },
        ]);
    });

    it("V6: yeni huni şablondan açılır — şablon adıyla seçilir, istekte kaynak yok", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        const form = within(screen.getByRole("form", { name: "Yeni huni" }));
        fireEvent.change(form.getByLabelText("Yeni huni adı"), {
            target: { value: "gece" },
        });
        const secim = form.getByLabelText<HTMLSelectElement>("Başlangıç");
        const secenek = within(secim).getByRole<HTMLOptionElement>("option", {
            name: "Uyku",
        });
        expect(secenek.closest("optgroup")?.label).toBe("Şablon");
        fireEvent.change(secim, { target: { value: secenek.value } });
        fireEvent.click(form.getByRole("button", { name: "Huni aç" }));
        await waitFor(() =>
            expect(konum()).toBe("/panel/huniler/keepchat/gece"),
        );
        expect(yazmalar(istekler)).toEqual([
            {
                yontem: "POST",
                adres: "/panel/api/uygulamalar/keepchat/huniler",
                jeton: "jeton-kabuk",
                govde: { ad: "gece", sablon: "uyku" },
            },
        ]);
    });

    it("yeni huni hatası gösterilir, sayfa değişmez", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        const form = within(screen.getByRole("form", { name: "Yeni huni" }));
        fireEvent.change(form.getByLabelText("Yeni huni adı"), {
            target: { value: "genel" },
        });
        fireEvent.change(form.getByLabelText("Başlangıç"), {
            target: { value: "" },
        });
        fireEvent.click(form.getByRole("button", { name: "Huni aç" }));
        expect((await screen.findByRole("alert")).textContent).toContain(
            "zaten var",
        );
        expect(yazmalar(istekler)[0]?.govde).toEqual({ ad: "genel" });
        expect(konum()).toBe("/panel/huniler");
    });

    it("kurallar: sıra, ilk/son taşıma kapalı, ekle/taşı/sil istekleri", async () => {
        const istekler = kur();
        await waitFor(() =>
            expect(
                document.querySelector('tr[data-huni="genel"]'),
            ).not.toBeNull(),
        );
        const kural = (id: number) =>
            within(
                document.querySelector<HTMLElement>(`tr[data-kural="${id}"]`)!,
            );
        expect(kural(4).getByText("k=fit")).toBeTruthy();
        expect(
            (
                kural(4).getByRole("button", {
                    name: "Yukarı taşı",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        expect(
            (
                kural(5).getByRole("button", {
                    name: "Aşağı taşı",
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);

        fireEvent.click(kural(5).getByRole("button", { name: "Yukarı taşı" }));
        await screen.findByText("Kural taşındı.");
        fireEvent.click(kural(4).getByRole("button", { name: "Sil" }));
        await screen.findByText("Kural silindi.");
        const form = within(screen.getByRole("form", { name: "Yeni kural" }));
        fireEvent.change(form.getByLabelText("Adreste"), {
            target: { value: "utm_campaign" },
        });
        fireEvent.change(form.getByLabelText("="), {
            target: { value: "kis" },
        });
        fireEvent.change(form.getByLabelText("→ Huni"), {
            target: { value: "bos" },
        });
        fireEvent.click(form.getByRole("button", { name: "Kural ekle" }));
        await screen.findByText("Kural eklendi.");

        expect(
            yazmalar(istekler).map(({ yontem, adres, govde }) => [
                yontem,
                adres,
                govde,
            ]),
        ).toEqual([
            [
                "POST",
                "/panel/api/uygulamalar/keepchat/kurallar/5/tasi",
                { yon: "yukari" },
            ],
            ["DELETE", "/panel/api/uygulamalar/keepchat/kurallar/4", null],
            [
                "POST",
                "/panel/api/uygulamalar/keepchat/kurallar",
                { parametre: "utm_campaign", deger: "kis", huni: "bos" },
            ],
        ]);
        expect(yazmalar(istekler).every((i) => i.jeton === "jeton-kabuk")).toBe(
            true,
        );
    });
});
