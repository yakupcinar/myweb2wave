// T2b: oynatıcıda özellik deposunun adları (sunucu: ziyaret_ozellikleri
// görünümü, core/src/Ozellik/Ozellikler.php). Girdi oynatıcının /ilerleme'ye
// gönderdiği olaylar — iki taraf aynı olaylardan aynı özellikleri üretir
// (ozellik-ornekleri.json ikisinde de ölçülür). Tarayıcı yalnız hunin kendi
// verisini bilir: odeme.* ve abonelik.* burada yok. Koşul motoru (T3) okur.

/** /ilerleme gövdesi (sözleşme): adıma ulaşma ya da adımın cevabı. */
export type Olay = {
    sira: number;
    adim: string;
    cevap?: string;
    cevaplar?: string[];
    cevapMetni?: string;
};

export type Ozellikler = Record<string, string | number | string[]>;

const UTM = ["source", "medium", "campaign", "content", "term"] as const;

export function ozellikler({
    huni,
    surum,
    adres,
    izin,
    olaylar,
    deneyler,
}: {
    huni?: string | undefined;
    surum?: number | undefined;
    /** location.search */
    adres: string;
    izin: "evet" | "hayir" | null;
    olaylar: Olay[];
    /** M4a: çekirdeğin tanımla verdiği atama (deney adı → varyant). */
    deneyler?: Record<string, string> | undefined;
}): Ozellikler {
    const o: Ozellikler = {};
    if (huni) o["huni"] = huni;
    if (surum) o["huni.surum"] = surum;
    const parametreler = new URLSearchParams(adres);
    for (const ad of UTM) {
        // lib/ziyaret.ts ile aynı: 500 karakter, boş gönderilmez.
        const deger = parametreler.get(`utm_${ad}`)?.slice(0, 500);
        if (deger) o[`utm.${ad}`] = deger;
    }
    if (izin) o["izin.reklam"] = izin;
    for (const [ad, varyant] of Object.entries(deneyler ?? {}))
        o[`deney.${ad}`] = varyant;
    // Sunucu: aynı sırada ilk yazılan adım kalır (ON CONFLICT DO NOTHING).
    const adimlar = new Map<number, string>();
    for (const olay of olaylar) {
        if (!adimlar.has(olay.sira)) adimlar.set(olay.sira, olay.adim);
        const anahtar = `cevap.${olay.adim}`;
        const secim = olay.cevap !== undefined ? [olay.cevap] : olay.cevaplar;
        if (secim) {
            // Sunucu listeyi siler ve yeniden yazar; COLLATE "C" = kod sırası.
            if (secim.length) o[anahtar] = [...new Set(secim)].sort();
            else delete o[anahtar];
        }
        if (olay.cevapMetni !== undefined) {
            if (olay.cevapMetni.trim()) o[anahtar] = olay.cevapMetni;
            else delete o[anahtar];
        }
    }
    if (adimlar.size) o["adim.son"] = adimlar.get(Math.max(...adimlar.keys()))!;
    return o;
}
