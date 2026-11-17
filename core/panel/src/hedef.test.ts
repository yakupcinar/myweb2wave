import type { AppState } from "@puckeditor/core";
import { expect, it } from "vitest";
import { tanimdanPuck, type Tanim } from "./donustur";
import { hedefSecenekleri, ozellikSecenekleri } from "./yapilandirma";

const tanim: Tanim = {
    sayfaBasligi: "X",
    adimlar: [
        { id: "giris", tur: "ekran", baslik: "A", bloklar: [], dugme: "B" },
        {
            id: "kiminle",
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
        { id: "ara", tur: "ekran", baslik: "C", bloklar: [], dugme: "D" },
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

// T3a: koşulun özellik listesi — sözlüğün hunide bilinen adları + bu ve
// önceki ekranların soruları (sonrakinin cevabı yok; çekirdek de reddeder).
it("koşul özellikleri: sabit adlar + bu ve önceki ekranların cevapları", () => {
    const soruEkrani = (id: string) => ({
        id,
        tur: "ekran" as const,
        baslik: "?",
        bloklar: [
            {
                tur: "soru" as const,
                secenekler: [
                    { id: "a", metin: "A" },
                    { id: "b", metin: "B" },
                ],
            },
        ],
    });
    const v = tanimdanPuck(
        {
            sayfaBasligi: "X",
            adimlar: [
                soruEkrani("s1"),
                {
                    id: "bilgi",
                    tur: "ekran",
                    baslik: "B",
                    bloklar: [],
                    dugme: "D",
                },
                soruEkrani("s2"),
                soruEkrani("s3"),
                tanim.adimlar.at(-1)!,
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
    // Ç91: sabit kısım sunucudan gelen listedir (elle kopya yok), etiket "açıklama (ad)".
    const sabit = [
        { ad: "utm.source", aciklama: "reklam kaynağı" },
        { ad: "adim.son", aciklama: "ulaşılan en ileri adım" },
    ];
    const secenekler = ozellikSecenekleri(
        { data: v } as unknown as AppState,
        String(v.content[2]!.props.id),
        true,
        sabit,
    );
    expect(secenekler).toEqual([
        { label: "reklam kaynağı (utm.source)", value: "utm.source" },
        { label: "ulaşılan en ileri adım (adim.son)", value: "adim.son" },
        { label: "Cevap: s1", value: "cevap.s1" },
        { label: "Cevap: s2", value: "cevap.s2" },
    ]);
    // Gösterim / koşullu değer: bu ekranın cevabı yok.
    expect(
        ozellikSecenekleri(
            { data: v } as unknown as AppState,
            String(v.content[2]!.props.id),
            false,
            sabit,
        ).map((o) => o.value),
    ).toEqual(["utm.source", "adim.son", "cevap.s1"]);
});
