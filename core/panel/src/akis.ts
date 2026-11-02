// Akış denetimleri (Ç55): düzenleyicide uyarı ve kolaylık; çekirdek yine
// yalnız ileri hedefi doğrular, ulaşılamayan adımı reddetmez (A13: uyarı).
import { BLOK_BILESENLERI, type HuniVerisi, type Tanim } from "./donustur";

/**
 * Baştan hiçbir yolla varılamayan adımların kimlikleri. Kural oynatıcıyla
 * aynı (Huni.tsx `ileri`): ekranda tek seçimli soru bloğu varsa seçenek
 * hedefleri, yoksa ekranın hedefi (yoksa sıradaki); yükleme sıradakine;
 * paywall son.
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
        const blokSorusu =
            adim.tur === "ekran"
                ? adim.bloklar.find((b) => b.tur === "soru")
                : undefined;
        if (blokSorusu && (blokSorusu.secimTuru ?? "tekli") === "tekli")
            for (const s of blokSorusu.secenekler ?? [])
                kuyruk.push(sira(s.hedef, i));
        else if (adim.tur === "ekran") kuyruk.push(sira(adim.hedef, i));
        else if (adim.tur !== "paywall") kuyruk.push(i + 1);
    }
    return adimlar.filter((_, i) => !gorulen.has(i)).map((a) => a.id);
}

/**
 * T1: Puck kök bölgesi bileşen kısıtlamıyor — ekranın yuvası dışına
 * bırakılan blokların türleri (kayıtta çekirdek reddeder).
 */
export function disaridakiBloklar(veri: HuniVerisi): string[] {
    return veri.content
        .filter((o) => BLOK_BILESENLERI.includes(o.type))
        .map((o) => o.type);
}

type Hedefli = { hedef?: string; secenekler?: { hedef?: string }[] };

/** T1: kökte adım olmayan (bloğun yuvadan taşmış hâli) öğenin kimliği yok. */
const kimligi = (o: { props: object }): string =>
    String((o.props as { kimlik?: unknown }).kimlik);

/**
 * Bir bloğun kimliği değişince (aynı Puck bloğu, eski ad artık hiçbir
 * blokta yok) ona işaret eden tanıtım ve seçenek hedeflerini yeni ada
 * taşır. Değişiklik yoksa null — çağıran gereksiz yazmasın.
 */
export function hedefleriYenidenAdlandir(
    once: HuniVerisi,
    sonra: HuniVerisi,
): HuniVerisi | null {
    const eskiAd = new Map(once.content.map((o) => [o.props.id, kimligi(o)]));
    const adlar = new Set(sonra.content.map(kimligi));
    const yeniAd = new Map<string, string>();
    for (const o of sonra.content) {
        const eski = eskiAd.get(o.props.id);
        const yeni = kimligi(o);
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
    const tasinmis = <P extends Hedefli>(p: P): P => ({
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
    });
    const content = sonra.content.map((o) => {
        const p = tasinmis(o.props as typeof o.props & Hedefli) as Hedefli & {
            bloklar?: { props: Hedefli }[];
        };
        return {
            ...o,
            props: {
                ...p,
                // T1b: ekranın yuvasındaki soru bloğu.
                ...(p.bloklar
                    ? {
                          bloklar: p.bloklar.map((b) => ({
                              ...b,
                              props: tasinmis(b.props),
                          })),
                      }
                    : {}),
            },
        } as typeof o;
    });
    return degisti ? { ...sonra, content } : null;
}
