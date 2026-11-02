import type { AppState } from "@puckeditor/core";
import { expect, it } from "vitest";
import { tanimdanPuck, type Tanim } from "./donustur";
import { hedefSecenekleri } from "./yapilandirma";

const tanim: Tanim = {
    sayfaBasligi: "X",
    adimlar: [
        { id: "giris", tur: "tanitim", baslik: "A", metin: "", dugme: "B" },
        {
            id: "kiminle",
            tur: "soru",
            soru: "?",
            secenekler: [
                { id: "a", metin: "A" },
                { id: "b", metin: "B" },
            ],
        },
        { id: "ara", tur: "tanitim", baslik: "C", metin: "", dugme: "D" },
        {
            id: "paywall",
            tur: "paywall",
            baslik: "P",
            metin: "",
            dugme: "Al",
            plan: "monthly",
        },
    ],
};
const veri = tanimdanPuck(tanim, {
    anaRenk: "#2563eb",
    arkaPlan: "#ffffff",
    metinRengi: "#111827",
    yaziTipi: "sistem",
    kose: 10,
});
const durum = { data: veri } as unknown as AppState;
const bilesen = (i: number): string => String(veri.content[i]?.props.id);

// H5: yalnız İLERİ — kendisi ve öncekiler listede yok (çekirdek de reddeder).
it("hedef listesi: sıradaki + yalnız sonraki adımlar", () => {
    expect(hedefSecenekleri(durum, bilesen(1)).map((o) => o.value)).toEqual([
        "",
        "ara",
        "paywall",
    ]);
    expect(hedefSecenekleri(durum, bilesen(0)).map((o) => o.value)).toEqual([
        "",
        "kiminle",
        "ara",
        "paywall",
    ]);
    expect(hedefSecenekleri(durum, bilesen(3))).toEqual([
        { label: "Sıradaki adım", value: "" },
    ]);
});

// T1b: soru bloğu kökte değil, ekranın yuvasında — liste ekranın sırasından.
it("hedef listesi: ekrandaki soru bloğu için ekrandan sonraki adımlar", () => {
    const ekranli = tanimdanPuck(
        {
            ...tanim,
            adimlar: [
                tanim.adimlar[0]!,
                {
                    id: "e",
                    tur: "ekran",
                    baslik: "?",
                    bloklar: [
                        {
                            tur: "soru",
                            secenekler: [
                                { id: "a", metin: "A" },
                                { id: "b", metin: "B" },
                            ],
                        },
                    ],
                },
                ...tanim.adimlar.slice(2),
            ],
        },
        {
            anaRenk: "#2563eb",
            arkaPlan: "#ffffff",
            metinRengi: "#111827",
            yaziTipi: "sistem",
            kose: 10,
        },
    );
    const blok = (
        ekranli.content[1]!.props as { bloklar: { props: { id: string } }[] }
    ).bloklar[0]!.props.id;
    expect(
        hedefSecenekleri({ data: ekranli } as unknown as AppState, blok).map(
            (o) => o.value,
        ),
    ).toEqual(["", "ara", "paywall"]);
});
