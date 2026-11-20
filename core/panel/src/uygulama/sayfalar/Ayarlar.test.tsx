// @vitest-environment happy-dom
// M9a (A23): ayarlar sayfası — yükler, düzenler, kabuğun CSRF jetonuyla PUT
// eder; sunucunun hatasını gösterir; menüde işaretli. Sunucu yerine sahte fetch.
// P7c: canlıya geçiş adımları sunucunun ölçtüğü durumla çizilir.
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

const KAYITLI = {
    ad: "KeepChat",
    sema: "keepchat",
    magazaAdresi: "",
    destekEpostasi: "",
};

/** Test ortamına benzer: Stripe test anahtarı, Meta dolu, gerisi eksik. */
const CANLI = {
    odeme: { tamam: true, kip: "test" },
    stripeBildirimleri: { tamam: false, son: null },
    eposta: { tamam: false },
    yasal: { tamam: false, eksikler: ["gizlilik"] },
    metaReklam: { tamam: true },
    gorevler: {
        tamam: false,
        eksikler: ["app:posta:dizi", "app:webhook:gonder"],
    },
};

/** BR1: KeepChat'in bugünkü hâline benzer — anahtar var, uygulamanın sunucusu hiç gelmedi. */
const BAGLANTI = {
    anahtar: { tamam: true },
    istek: { tamam: false, son: null },
    baglama: { tamam: false, sayi: 0, son: null },
    girisBaglantisi: { tamam: false, son: null },
    revenuecat: { tamam: false, acik: true, gonderilen: 0, bekleyen: 0 },
    magaza: { tamam: false },
};

function kur(
    put: (govde: unknown) => Response = (g) => new Response(JSON.stringify(g)),
    canli: unknown = CANLI,
    baglanti: unknown = BAGLANTI,
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
            if (istek.url.endsWith("/canliya-gecis"))
                return new Response(JSON.stringify(canli));
            if (istek.url.endsWith("/baglanti"))
                return new Response(JSON.stringify(baglanti));
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
                .getByRole("link", { name: "Uygulama" })
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
        expect(
            await screen.findByText('"genel" hunisinden · 10 Kasım'),
        ).toBeTruthy();
        expect(
            screen.getByText(
                "Tema ve paywall yeni açılan hunilere kopyalanır. Var olan huniler değişmez.",
            ),
        ).toBeTruthy();
        fireEvent.click(screen.getByRole("button", { name: "Kaldır" }));
        await screen.findByText(/Yok — yeni huniler boş başlar/);
        expect(istekler.find((i) => i.yontem === "DELETE")).toEqual({
            yontem: "DELETE",
            adres: "/panel/api/uygulamalar/keepchat/varsayilanlar",
            jeton: "jeton-kabuk",
            govde: null,
        });
    });

    it("P7c: canlıya geçiş — sayı, çubuk; eksik adımda Aç / Nasıl yapılır, tamamda yok", async () => {
        kur();
        expect(await screen.findByText("6 adımdan 2'si tamam")).toBeTruthy();
        const cubuk = screen.getByRole("progressbar", {
            name: "Canlıya geçiş",
        });
        expect(cubuk.getAttribute("aria-valuenow")).toBe("2");
        const satirlar = within(
            screen.getByRole("region", { name: "Canlıya geçiş" }),
        ).getAllByRole("listitem");
        expect(
            satirlar.map(
                (s) =>
                    within(s).getByRole("img").getAttribute("aria-label") ?? "",
            ),
        ).toEqual([
            "Tamam",
            "Yapılmadı",
            "Yapılmadı",
            "Yapılmadı",
            "Tamam",
            "Yapılmadı",
        ]);
        const [odeme, bildirim, , yasal, meta, gorev] = satirlar as [
            HTMLElement,
            HTMLElement,
            HTMLElement,
            HTMLElement,
            HTMLElement,
            HTMLElement,
        ];
        expect(odeme.textContent).toContain("Test modunda.");
        for (const s of [odeme, meta])
            expect(
                within(s).queryAllByRole("button").length +
                    within(s).queryAllByRole("link").length,
            ).toBe(0);
        expect(yasal.textContent).toContain(
            "Yayında değil: Gizlilik politikası.",
        );
        expect(
            within(yasal)
                .getByRole("link", { name: "Aç" })
                .getAttribute("href"),
        ).toBe("/panel/yasal");
        expect(gorev.textContent).toContain(
            "Çalışmayan: hatırlatma e-postaları, webhook gönderimi.",
        );

        const nasil = within(bildirim).getByRole("button", {
            name: "Nasıl yapılır",
        });
        expect(nasil.getAttribute("aria-expanded")).toBe("false");
        expect(bildirim.textContent).not.toContain("STRIPE_WEBHOOK_SECRET");
        fireEvent.click(nasil);
        expect(nasil.getAttribute("aria-expanded")).toBe("true");
        expect(bildirim.textContent).toContain("STRIPE_WEBHOOK_SECRET");
    });

    // BR1: "kod var, canlıda kapalı" (Ç158) ilk bakışta görünsün; isteğe bağlı mağaza adımı sayılmaz.
    it("BR1: uygulama bağlantısı — ölçülen adımlar, isteğe bağlı sayaç dışı, eksikte nasıl yapılır", async () => {
        kur();
        expect(await screen.findByText("5 adımdan 1'i tamam")).toBeTruthy();
        const satirlar = within(
            screen.getByRole("list", { name: "Uygulama bağlantısı" }),
        ).getAllByRole("listitem");
        expect(
            satirlar.map(
                (s) =>
                    within(s).getByRole("img").getAttribute("aria-label") ?? "",
            ),
        ).toEqual([
            "Tamam",
            "Yapılmadı",
            "Yapılmadı",
            "Yapılmadı",
            "Yapılmadı",
            "Yapılmadı",
        ]);
        const [, istek, baglama, , rc, magaza] = satirlar as HTMLElement[];
        expect(istek!.textContent).toContain(
            "henüz geçerli anahtarla istek gelmedi",
        );
        expect(baglama!.textContent).toContain("Henüz kimse bağlanmadı");
        expect(rc!.textContent).toContain("Açık; bağlı bir kişinin");
        expect(magaza!.textContent).toContain("İsteğe bağlı");
        fireEvent.click(
            within(istek!).getByRole("button", { name: "Nasıl yapılır" }),
        );
        expect(istek!.textContent).toContain("hak sorgusu");
    });

    it("BR1: bağlı uygulamada tarih ve sayılar; RevenueCat kapalıysa söyler", async () => {
        kur(undefined, CANLI, {
            anahtar: { tamam: true },
            istek: { tamam: true, son: "2026-11-20T09:00:00+00:00" },
            baglama: { tamam: true, sayi: 3, son: "2026-11-19T08:00:00+00:00" },
            girisBaglantisi: { tamam: true, son: "2026-11-18T08:00:00+00:00" },
            revenuecat: {
                tamam: false,
                acik: false,
                gonderilen: 0,
                bekleyen: 0,
            },
            magaza: { tamam: true },
        });
        expect(await screen.findByText("5 adımdan 4'ü tamam")).toBeTruthy();
        const satirlar = within(
            screen.getByRole("list", { name: "Uygulama bağlantısı" }),
        ).getAllByRole("listitem");
        expect(satirlar[1]!.textContent).toContain("Son istek 20 Kasım.");
        expect(satirlar[2]!.textContent).toContain("3 kişi bağlı");
        expect(satirlar[4]!.textContent).toContain(
            "Kapalı: RevenueCat Stripe anahtarı yok.",
        );
    });

    it("P7c: hepsi tamamsa 6'sı; hiçbiri değilse 0'ı; son bildirim tarihi", async () => {
        kur(undefined, {
            odeme: { tamam: true, kip: "canli" },
            stripeBildirimleri: {
                tamam: true,
                son: "2026-11-10T09:00:00+00:00",
            },
            eposta: { tamam: true },
            yasal: { tamam: true, eksikler: [] },
            metaReklam: { tamam: true },
            gorevler: { tamam: true, eksikler: [] },
        });
        expect(await screen.findByText("6 adımdan 6'sı tamam")).toBeTruthy();
        expect(screen.getByText(/Canlı modda/)).toBeTruthy();
        expect(screen.getByText(/^Son bildirim 10 Kasım\./)).toBeTruthy();
        cleanup();
        kur(undefined, {
            odeme: { tamam: false, kip: null },
            stripeBildirimleri: { tamam: false, son: null },
            eposta: { tamam: false },
            yasal: { tamam: false, eksikler: ["sartlar", "gizlilik"] },
            metaReklam: { tamam: false },
            gorevler: { tamam: false, eksikler: [] },
        });
        expect(await screen.findByText("6 adımdan 0'ı tamam")).toBeTruthy();
        expect(screen.getByText(/Anahtar yok/)).toBeTruthy();
    });

    it("P7e: her alan ortak stilde (ui/Alan), her kutu vurgu renginde", async () => {
        kur();
        await screen.findByDisplayValue("KeepChat");
        await screen.findByText("6 adımdan 2'si tamam");
        expect(ciplakAlanlar(document.body)).toEqual([]);
    });
});
