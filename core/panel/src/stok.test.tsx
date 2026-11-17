// @vitest-environment happy-dom
// V3b: görsel alanında stok arama — sorgu çekirdeğe, tıklanan görsel jetonla
// içe alınır, alan yeni kimliği alır; stok görselin fotoğrafçısı ve lisansı
// görünür; Pexels'e bağlantı (API kuralı) hep var.
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import type { ReactNode } from "react";
import { afterEach, expect, it, vi } from "vitest";
import { yapilandirma, type Ortam } from "./yapilandirma";

const ortam: Ortam = {
    planlar: ["monthly"],
    yaziTipleri: ["sistem"],
    hazirTemalar: [],
    medyaAdresi: "/panel/medya/keepchat",
    jeton: "jeton-x",
    ozellikler: [],
};
const ID = "b".repeat(64);

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

function kur(deger = "") {
    const istekler: { yontem: string; adres: string; jeton: string | null }[] =
        [];
    let alindi = false;
    vi.stubGlobal(
        "fetch",
        vi.fn(async (adres: string, init?: RequestInit) => {
            const yontem = init?.method ?? "GET";
            istekler.push({
                yontem,
                adres,
                jeton: new Headers(init?.headers).get("X-CSRF-Token"),
            });
            const j = (o: unknown, durum = 200) =>
                new Response(JSON.stringify(o), { status: durum });
            if (adres.startsWith("/panel/medya/keepchat/stok?"))
                return j({
                    sonuclar: [
                        {
                            kimlik: 101,
                            kucuk: "https://images.pexels.com/k.jpeg",
                            yazar: "Ayşe",
                            aciklama: "Uyuyan kadın",
                        },
                    ],
                });
            if (adres === "/panel/medya/keepchat/stok/101") {
                alindi = true;
                return j({ id: ID }, 201);
            }
            return j({
                gorseller:
                    alindi || deger
                        ? [
                              {
                                  id: ID,
                                  ozgun_ad: "pexels-101.jpg",
                                  genislik: 1067,
                                  yukseklik: 1600,
                                  kaynak: {
                                      adres: "https://www.pexels.com/photo/101/",
                                      yazar: "Ayşe",
                                      lisans: "Pexels Lisansı",
                                      lisansAdresi:
                                          "https://www.pexels.com/license/",
                                  },
                              },
                          ]
                        : [],
            });
        }),
    );
    const degisti = vi.fn();
    const alan = yapilandirma(ortam).root!.fields!.logo as unknown as {
        render: (p: {
            value: string;
            onChange: (v: string) => void;
            id: string;
        }) => ReactNode;
    };
    render(alan.render({ value: deger, onChange: degisti, id: "logo" }));
    return { istekler, degisti };
}

it("arar, tıklananı jetonla içe alır, alan yeni kimliği alır", async () => {
    const { istekler, degisti } = kur();
    fireEvent.change(screen.getByLabelText("Stok fotoğraf ara"), {
        target: { value: "sleeping woman" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Ara" }));
    fireEvent.click(await screen.findByTitle("Uyuyan kadın — Ayşe"));
    await waitFor(() => expect(degisti).toHaveBeenCalledWith(ID));
    expect(istekler.find((i) => i.adres.includes("/stok?"))?.adres).toBe(
        "/panel/medya/keepchat/stok?q=sleeping+woman",
    );
    expect(istekler.find((i) => i.yontem === "POST")).toEqual({
        yontem: "POST",
        adres: "/panel/medya/keepchat/stok/101",
        jeton: "jeton-x",
    });
    expect(
        screen
            .getByRole("link", { name: "Fotoğraflar: Pexels" })
            .getAttribute("href"),
    ).toBe("https://www.pexels.com");
});

it("seçili stok görselin fotoğrafçısı ve lisansı bağlantılı", async () => {
    kur(ID);
    expect(
        (await screen.findByRole("link", { name: "Ayşe" })).getAttribute(
            "href",
        ),
    ).toBe("https://www.pexels.com/photo/101/");
    expect(
        screen
            .getByRole("link", { name: "Pexels Lisansı" })
            .getAttribute("href"),
    ).toBe("https://www.pexels.com/license/");
});
