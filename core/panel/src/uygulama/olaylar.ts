// Giden webhook olaylarının Türkçe adları ve grupları (P7d, A25). Sözlük
// sunucuda (Olaylar::SOZLUK); her ad burada da olmalı — PanelOlayAdlariTest
// ölçer (kural 7). Bilinmeyen ad "Diğer"de teknik adıyla görünür.
export const OLAY_GRUPLARI: { ad: string; olaylar: [string, string][] }[] = [
    {
        ad: "Huni",
        olaylar: [
            ["huni.ziyaret", "Ziyaret başladı"],
            ["adim.goruldu", "Adım görüldü"],
            ["cevap.verildi", "Cevap verildi"],
            ["izin.verildi", "Reklam ölçümüne izin verildi"],
            ["izin.reddedildi", "Reklam ölçümü reddedildi"],
        ],
    },
    {
        ad: "Ödeme",
        olaylar: [
            ["odeme.baslatildi", "Ödeme sayfası açıldı"],
            ["odeme.tamamlandi", "Ödeme tamamlandı"],
            ["odeme.fatura", "Fatura tahsil edildi"],
            ["odeme.iade", "İade edildi"],
        ],
    },
    {
        ad: "Abonelik",
        olaylar: [["abonelik.durum", "Abonelik durumu değişti"]],
    },
];

const ADLAR = new Map(OLAY_GRUPLARI.flatMap((g) => g.olaylar));

export function olayAdi(olay: string): string {
    return ADLAR.get(olay) ?? olay;
}

/** Sunucunun listesini gruplara böler; grupta olmayanlar "Diğer"de. */
export function gruplar(
    sozluk: string[],
): { ad: string; olaylar: [string, string][] }[] {
    const sonuc = OLAY_GRUPLARI.map((g) => ({
        ad: g.ad,
        olaylar: g.olaylar.filter(([o]) => sozluk.includes(o)),
    }));
    const diger = sozluk.filter((o) => !ADLAR.has(o));
    if (diger.length)
        sonuc.push({ ad: "Diğer", olaylar: diger.map((o) => [o, o]) });
    return sonuc.filter((g) => g.olaylar.length);
}

/** Hedef satırı için: grubun hepsi seçiliyse "Ödeme: tümü", değilse adlar. */
export function olayOzeti(secili: string[], sozluk: string[]): string {
    return gruplar(sozluk)
        .flatMap((g) => {
            const bu = g.olaylar.filter(([o]) => secili.includes(o));
            if (!bu.length) return [];
            return bu.length === g.olaylar.length && bu.length > 1
                ? [`${g.ad}: tümü`]
                : bu.map(([, ad]) => ad);
        })
        .join(" · ");
}
