// @vitest-environment happy-dom
// A3: düzenleyicideki AI sohbeti — akış okuma, kapalı/kaydedilmemiş durumlar,
// taslağın tuvale konması ve geri alma. Sunucu yerine sahte fetch; olaylar
// sözleşmedeki biçimde (PanelAiOlayi).
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { sseOku, type AiOlayi } from "./ai";
import { AiSohbeti } from "./AiSohbeti";
import type { HuniVerisi, Tanim } from "./donustur";

const TANIM: Tanim = {
    sayfaBasligi: "Yeni",
    adimlar: [
        {
            id: "karsilama",
            tur: "tanitim",
            baslik: "Merhaba",
            metin: "Başla.",
            dugme: "Başla",
        },
        {
            id: "paywall",
            tur: "paywall",
            plan: "monthly",
            baslik: "Plan",
            metin: "Abone ol.",
            dugme: "Abone ol",
        },
    ],
};
const ONCEKI = {
    root: { props: { sayfaBasligi: "Eski" } },
    content: [],
} as unknown as HuniVerisi;

/** Metni verilen bayt sınırlarında parçalayan akış (ağ parçalarını taklit eder). */
function akis(metin: string, parca = 7): ReadableStream<Uint8Array> {
    const bayt = new TextEncoder().encode(metin);
    let i = 0;
    return new ReadableStream({
        pull(c) {
            if (i >= bayt.length) return c.close();
            c.enqueue(bayt.slice(i, i + parca));
            i += parca;
        },
    });
}
const sse = (olaylar: AiOlayi[]): string =>
    olaylar
        .map((o) => `event: ${o.tur}\ndata: ${JSON.stringify(o)}\n\n`)
        .join("");

function kur({
    acik = true,
    kayitli = true,
    sohbet = (): Response =>
        new Response(
            akis(
                sse([
                    { tur: "arac", ad: "taslak_kaydet" },
                    { tur: "arac_sonucu", ad: "taslak_kaydet", hata: true },
                    { tur: "arac", ad: "taslak_kaydet" },
                    { tur: "taslak", imza: "yeni-imza", tanim: TANIM },
                    { tur: "arac_sonucu", ad: "taslak_kaydet", hata: false },
                    { tur: "metin", metin: "Başlığı “Yeni” yaptım." },
                    {
                        tur: "bitti",
                        sonuc: "tamam",
                        kaydedildi: true,
                        tur_sayisi: 3,
                        maliyetMikrodolar: 24000,
                    },
                ]),
            ),
            { headers: { "Content-Type": "text/event-stream" } },
        ),
} = {}) {
    const fetchSahte = vi.fn(async (adres: string, _secenek?: RequestInit) =>
        adres === "/durum"
            ? new Response(JSON.stringify({ acik, model: "claude-opus-5-5" }))
            : sohbet(),
    );
    vi.stubGlobal("fetch", fetchSahte);
    const taslakGeldi = vi.fn();
    const geriAl = vi.fn();
    render(
        <AiSohbeti
            durumAdresi="/durum"
            sohbetAdresi="/sohbet"
            jeton="jeton-1"
            kayitliMi={() => kayitli}
            veriAl={() => ONCEKI}
            taslakGeldi={taslakGeldi}
            geriAl={geriAl}
        />,
    );
    return { fetchSahte, taslakGeldi, geriAl };
}

async function gonder(istem = "Başlığı Yeni yap"): Promise<void> {
    fireEvent.change(await screen.findByLabelText("İstek"), {
        target: { value: istem },
    });
    fireEvent.click(screen.getByRole("button", { name: "Gönder" }));
}

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe("sseOku", () => {
    it("olay ve UTF-8 baytları parçalar arasında bölünse de doğru okur", async () => {
        const olaylar: AiOlayi[] = [];
        const gelen: AiOlayi[] = [
            { tur: "metin", metin: "Çoklu seçim ğüşıöç" },
            {
                tur: "bitti",
                sonuc: "tamam",
                kaydedildi: false,
                tur_sayisi: 1,
                maliyetMikrodolar: null,
            },
        ];
        for (const parca of [1, 3, 5, 64]) {
            olaylar.length = 0;
            await sseOku(akis(sse(gelen), parca), (o) => olaylar.push(o));
            expect(olaylar).toEqual(gelen);
        }
    });
});

describe("sseOku canlı", () => {
    it("olay akış BİTMEDEN bildirilir (yönetici ilerlemeyi canlı görür)", async () => {
        let kapat: () => void = () => {};
        const govde = new ReadableStream<Uint8Array>({
            start(c) {
                c.enqueue(
                    new TextEncoder().encode(
                        sse([{ tur: "arac", ad: "taslak_kaydet" }]),
                    ),
                );
                kapat = () => c.close();
            },
        });
        const gelen: AiOlayi[] = [];
        const bitti = sseOku(govde, (o) => gelen.push(o));
        await new Promise((r) => setTimeout(r, 20));
        expect(gelen).toEqual([{ tur: "arac", ad: "taslak_kaydet" }]);
        kapat();
        await bitti;
    });
});

describe("AiSohbeti", () => {
    it("AI kapalıysa nedenini söyler, istek formu yok", async () => {
        const { fetchSahte } = kur({ acik: false });
        expect(await screen.findByRole("status")).toHaveProperty(
            "textContent",
            expect.stringContaining("ANTHROPIC_API_KEY"),
        );
        expect(screen.queryByLabelText("İstek")).toBeNull();
        expect(fetchSahte).toHaveBeenCalledTimes(1);
    });

    it("kaydedilmemiş değişiklik varken istek gitmez", async () => {
        const { fetchSahte } = kur({ kayitli: false });
        await gonder();
        expect(await screen.findByText(/önce kaydet/)).toBeTruthy();
        expect(fetchSahte.mock.calls.map((c) => c[0])).toEqual(["/durum"]);
    });

    it("akış: adımlar, hata→düzeltme, taslak tuvale, özet; geri al önceki veriyi kaydeder", async () => {
        const { fetchSahte, taslakGeldi, geriAl } = kur();
        await gonder();

        await screen.findByText(/Taslağa uygulandı · 3 tur · ~0\.024 \$/);
        const [adres, secenek] = fetchSahte.mock.calls[1] ?? [];
        expect(adres).toBe("/sohbet");
        expect(secenek?.headers).toMatchObject({ "X-CSRF-Token": "jeton-1" });
        expect(JSON.parse(String(secenek?.body))).toEqual({
            istem: "Başlığı Yeni yap",
        });
        expect(taslakGeldi).toHaveBeenCalledWith(TANIM, "yeni-imza");
        expect(
            screen.getAllByRole("listitem").map((l) => l.textContent),
        ).toEqual([
            "Başlığı Yeni yap",
            "Taslak kaydediliyor — hata, düzeltiliyor",
            "Taslak kaydediliyor ✓",
            "Başlığı “Yeni” yaptım.",
            "Taslağa uygulandı · 3 tur · ~0.024 $. Kontrol edip yayınla.",
        ]);

        fireEvent.click(
            screen.getByRole("button", { name: "Bu değişikliği geri al" }),
        );
        expect(geriAl).toHaveBeenCalledWith(ONCEKI);
        expect(
            screen.queryByRole("button", { name: "Bu değişikliği geri al" }),
        ).toBeNull();
    });

    it("sunucu reddederse (ör. saatlik sınır) nedeni gösterir, geri al yok", async () => {
        const { taslakGeldi } = kur({
            sohbet: () =>
                new Response(
                    JSON.stringify({
                        hatalar: ["Saatlik AI isteği sınırı doldu."],
                    }),
                    { status: 429 },
                ),
        });
        await gonder();
        await waitFor(() =>
            expect(
                screen.getByText("Saatlik AI isteği sınırı doldu."),
            ).toBeTruthy(),
        );
        expect(taslakGeldi).not.toHaveBeenCalled();
        expect(
            screen.queryByRole("button", { name: "Bu değişikliği geri al" }),
        ).toBeNull();
    });
});
