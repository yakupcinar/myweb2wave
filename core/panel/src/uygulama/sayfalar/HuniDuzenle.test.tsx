// @vitest-environment happy-dom
// P3: düzenleyici sayfası — açılış ucundan gelen veri düzenleyiciye aynen
// geçer (Puck burada çizilmez: Duzenleyici yerine kaydedici); hata ve geri
// bağlantısı.
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";

const gelen = vi.hoisted(() => ({ props: [] as unknown[] }));
vi.mock("../../duzenleyici", () => ({
    Duzenleyici: (p: unknown) => {
        gelen.props.push(p);
        return <p>düzenleyici açık</p>;
    },
}));

const { yollar } = await import("../main");

const ACILIS = {
    baslik: "keepchat · genel",
    taslak: { sayfaBasligi: "X", adimlar: [] },
    imza: "a".repeat(64),
    planlar: ["monthly"],
    temaVarsayilan: { anaRenk: "#000000" },
    yaziTipleri: ["sistem"],
    jeton: "jeton-duzenleyici",
    adresler: {
        kaydet: "/panel/huniler/keepchat/genel/taslak",
        durum: "/panel/huniler/keepchat/genel/durum",
        medya: "/panel/medya/keepchat",
        aiDurum: "/panel/api/ai/durum",
        aiSohbet: "/panel/api/ai/huniler/keepchat/genel/sohbet",
    },
};

function kur(durum = 200) {
    const istenen: string[] = [];
    vi.stubGlobal(
        "fetch",
        vi.fn(async (istek: Request) => {
            istenen.push(new URL(istek.url, "http://x").pathname);
            return new Response(
                JSON.stringify(durum === 200 ? ACILIS : { hatalar: ["yok"] }),
                { status: durum },
            );
        }),
    );
    render(
        <QueryClientProvider
            client={
                new QueryClient({
                    defaultOptions: { queries: { retry: false } },
                })
            }
        >
            <RouterProvider
                router={createMemoryRouter(yollar, {
                    initialEntries: ["/panel/huniler/keepchat/genel"],
                })}
            />
        </QueryClientProvider>,
    );
    return istenen;
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    gelen.props = [];
});

describe("P3: düzenleyici sayfası", () => {
    it("açılış verisi düzenleyiciye aynen geçer; sol menü yok, geri bağlantısı var", async () => {
        const istenen = kur();
        await screen.findByText("düzenleyici açık");
        expect(istenen).toEqual([
            "/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici",
        ]);
        expect(gelen.props.at(-1)).toEqual({
            taslak: ACILIS.taslak,
            varsayilanTema: ACILIS.temaVarsayilan,
            ortam: {
                planlar: ["monthly"],
                yaziTipleri: ["sistem"],
                medyaAdresi: "/panel/medya/keepchat",
                jeton: "jeton-duzenleyici",
            },
            kaydetAdresi: ACILIS.adresler.kaydet,
            ilkImza: ACILIS.imza,
            durumAdresi: ACILIS.adresler.durum,
            baslik: "keepchat · genel",
            ai: {
                durumAdresi: ACILIS.adresler.aiDurum,
                sohbetAdresi: ACILIS.adresler.aiSohbet,
            },
        });
        expect(
            screen
                .getByRole("link", { name: "← Huniler ve kurallar" })
                .getAttribute("href"),
        ).toBe("/panel/huniler");
        expect(screen.queryByRole("navigation", { name: "Panel" })).toBeNull();
    });

    it("huni yoksa hata, düzenleyici kurulmaz", async () => {
        kur(404);
        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toContain(
                "Huni açılamadı",
            ),
        );
        expect(gelen.props).toEqual([]);
    });
});

// P3: panelin stil sayfası düzenleyici açıkken kapalı, çıkınca eski hâlinde;
// gövde kuralı yalnız açıkken (ölçüm: p3-duzenleyici-sayfasi.md).
describe("P3: stil yalıtımı", () => {
    it("düzenleyicinin stil sayfası yalnız açıkken etkin; çıkınca kapalı (panel değişkenlerini ezmesin)", async () => {
        const { useYalitim } = await import("./HuniDuzenle");
        // Düzenleyici parçası yüklenince sayfada kalır; huni.css :root --metin'i
        // panelinkiyle aynı ad (ölçüldü: dönüşte panel yazısı koyu zeminde koyu).
        const duzenleyici = document.createElement("style");
        duzenleyici.textContent =
            "#duzenleyici-stil-isareti { --duzenleyici-stil: 1; } :root { --metin: #111827; }";
        document.head.append(duzenleyici);
        const Deneme = () => {
            useYalitim();
            return <p>hazır</p>;
        };
        const ilk = render(<Deneme />);
        await screen.findByText("hazır");
        expect(duzenleyici.hasAttribute("media")).toBe(false);
        ilk.unmount();
        expect(duzenleyici.getAttribute("media")).toBe("not all");
        // Yeniden açılınca (parça zaten yüklü) yine etkin.
        const ikinci = render(<Deneme />);
        await screen.findByText("hazır");
        expect(duzenleyici.hasAttribute("media")).toBe(false);
        ikinci.unmount();
        duzenleyici.remove();
    });

    it("açıkken panel sayfası media=not all + gövde kuralı; kapanınca geri", async () => {
        const { useYalitim } = await import("./HuniDuzenle");
        const panel = document.createElement("style");
        panel.textContent =
            "#panel-stil-isareti { --panel-stil: 1; } body { color: red; }";
        const baska = document.createElement("style");
        baska.textContent = ".puck { color: blue; }";
        document.head.append(panel, baska);
        const Deneme = () => {
            useYalitim();
            return <p>hazır</p>;
        };
        const { unmount } = render(<Deneme />);
        await screen.findByText("hazır");
        expect(panel.getAttribute("media")).toBe("not all");
        expect(baska.hasAttribute("media")).toBe(false);
        const govde = () =>
            [...document.head.querySelectorAll("style")].filter((s) =>
                s.textContent?.includes("font: 15px/1.45"),
            );
        expect(govde()).toHaveLength(1);
        unmount();
        expect(panel.hasAttribute("media")).toBe(false);
        expect(govde()).toHaveLength(0);
        panel.remove();
        baska.remove();
    });
});
