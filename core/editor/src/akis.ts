// Akış denetimleri (Ç55): düzenleyicide uyarı ve kolaylık; çekirdek yine
// yalnız ileri hedefi doğrular, ulaşılamayan adımı reddetmez (A13: uyarı).
import type { HuniVerisi, Tanim } from "./donustur";

/**
 * Baştan hiçbir yolla varılamayan adımların kimlikleri. Kural oynatıcıyla
 * aynı (Huni.tsx `ileri`): hedef varsa ona, yoksa sıradakine; çoklu/metin
 * soru ve yorum hep sıradakine; paywall son.
 */
export function ulasilamayanlar(tanim: Tanim): string[] {
    const { adimlar } = tanim;
    const sira = (hedef: string | undefined, i: number): number =>
        hedef ? adimlar.findIndex((a) => a.id === hedef) : i + 1;
    const gorulen = new Set<number>();
    const kuyruk = [0];
    while (kuyruk.length) {
        const i = kuyruk.pop()!;
        const adim = adimlar[i];
        if (!adim || gorulen.has(i)) continue;
        gorulen.add(i);
        if (adim.tur === "tanitim") kuyruk.push(sira(adim.hedef, i));
        else if (adim.tur === "soru" && !adim.secimTuru)
            for (const s of adim.secenekler ?? [])
                kuyruk.push(sira(s.hedef, i));
        else if (adim.tur !== "paywall") kuyruk.push(i + 1);
    }
    return adimlar.filter((_, i) => !gorulen.has(i)).map((a) => a.id);
}

type Hedefli = { hedef?: string; secenekler?: { hedef?: string }[] };

/**
 * Bir bloğun kimliği değişince (aynı Puck bloğu, eski ad artık hiçbir
 * blokta yok) ona işaret eden tanıtım ve seçenek hedeflerini yeni ada
 * taşır. Değişiklik yoksa null — çağıran gereksiz yazmasın.
 */
export function hedefleriYenidenAdlandir(
    once: HuniVerisi,
    sonra: HuniVerisi,
): HuniVerisi | null {
    const eskiAd = new Map(
        once.content.map((o) => [o.props.id, String(o.props.kimlik)]),
    );
    const adlar = new Set(sonra.content.map((o) => String(o.props.kimlik)));
    const yeniAd = new Map<string, string>();
    for (const o of sonra.content) {
        const eski = eskiAd.get(o.props.id);
        const yeni = String(o.props.kimlik);
        if (eski && eski !== yeni && !adlar.has(eski)) yeniAd.set(eski, yeni);
    }
    if (!yeniAd.size) return null;
    let degisti = false;
    const tasi = (hedef: string | undefined): string | undefined => {
        const yeni = hedef ? yeniAd.get(hedef) : undefined;
        if (!yeni) return hedef;
        degisti = true;
        return yeni;
    };
    const content = sonra.content.map((o) => {
        const p = o.props as typeof o.props & Hedefli;
        return {
            ...o,
            props: {
                ...p,
                ...("hedef" in p ? { hedef: tasi(p.hedef) } : {}),
                ...(p.secenekler
                    ? {
                          secenekler: p.secenekler.map((s) => ({
                              ...s,
                              hedef: tasi(s.hedef),
                          })),
                      }
                    : {}),
            },
        } as typeof o;
    });
    return degisti ? { ...sonra, content } : null;
}
