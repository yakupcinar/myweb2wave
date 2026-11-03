// Akış denetimleri (Ç55): düzenleyicide uyarı ve kolaylık; çekirdek yine
// yalnız ileri hedefi doğrular, ulaşılamayan adımı reddetmez (A13: uyarı).
import {
    BLOK_BILESENLERI,
    type EkranProps,
    type HuniVerisi,
    type SoruBloguProps,
    type Tanim,
} from "./donustur";

/**
 * Ç87 + Ç84: ekran yuvadaki soru bloğuna göre düğmesini ayarlar. Tek seçimde
 * düğme yok (seçenek geçirir; çekirdek düğmeyi reddeder); çoklu / serbest
 * yanıtta düğme gerekir ve hunide ilk açılışta kapalı başlar — önizleme de.
 * Puck yuvadaki blok değişince üst bileşenin resolveData'sını ÇAĞIRMIYOR
 * (tarayıcıda ölçüldü) — bu yüzden bekçide, her veri değişiminde. Değişiklik
 * yoksa null.
 */
export function ekranDugmeleri(veri: HuniVerisi): HuniVerisi | null {
    let degisti = false;
    const content = veri.content.map((o) => {
        if (o.type !== "Ekran") return o;
        const p = o.props as EkranProps;
        const soru = (
            (p.bloklar ?? []) as unknown as {
                type: string;
                props: Partial<SoruBloguProps>;
            }[]
        ).find((b) => b.type === "SoruBlogu")?.props;
        const tur = soru ? (soru.secimTuru ?? "tekli") : null;
        const dugme =
            tur === "tekli" ? "" : tur && !p.dugme ? "Devam" : p.dugme;
        const kapali =
            tur === "coklu" ||
            ((tur === "metin" || tur === "eposta") &&
                soru?.yanitZorunlu === "zorunlu");
        if (dugme === p.dugme && kapali === (p.onizlemeKapali ?? false))
            return o;
        degisti = true;
        return {
            ...o,
            props: { ...p, dugme, onizlemeKapali: kapali },
        } as typeof o;
    });
    return degisti ? { ...veri, content } : null;
}

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
        // T3a: her kural hedefi de bir yol (koşul hangi ziyaretçide tutacak bilinmez).
        if (adim.tur === "ekran")
            for (const k of adim.yonlendirme ?? [])
                kuyruk.push(sira(k.hedef, i));
        // T3b: koşulu tutmazsa ekran atlanır → sıradaki adım da yol.
        if (adim.tur === "ekran" && adim.goster) kuyruk.push(i + 1);
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

type Hedefli = {
    hedef?: string;
    secenekler?: { hedef?: string }[];
    yonlendirme?: { hedef: string; kosullar: { ozellik: string }[] }[];
    gosterKosullar?: { ozellik: string }[];
    degerler?: { kosullar: { ozellik: string }[] }[];
};

/** T1: kökte adım olmayan (bloğun yuvadan taşmış hâli) öğenin kimliği yok. */
const kimligi = (o: { props: object }): string =>
    String((o.props as { kimlik?: unknown }).kimlik);

/**
 * Bir bloğun kimliği değişince (aynı Puck bloğu, eski ad artık hiçbir
 * blokta yok) ona işaret eden ekran, seçenek ve kural hedeflerini ve
 * koşullardaki cevap adını yeni ada taşır. Değişiklik yoksa null — çağıran gereksiz yazmasın.
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
    const cevabiTasi = <C extends { ozellik: string }>(c: C): C => {
        const yeni = c.ozellik.startsWith("cevap.")
            ? tasi(c.ozellik.slice(6))
            : undefined;
        return yeni ? { ...c, ozellik: `cevap.${yeni}` } : c;
    };
    const tasinmis = <P extends Hedefli>(p: P): P => ({
        ...p,
        ...("hedef" in p ? { hedef: tasi(p.hedef) } : {}),
        // T3a: kural hedefi ve koşuldaki cevap.<eski> (taşınmasa kural hiç tutmazdı).
        ...(p.yonlendirme
            ? {
                  yonlendirme: p.yonlendirme.map((k) => ({
                      ...k,
                      hedef: tasi(k.hedef) ?? k.hedef,
                      kosullar: k.kosullar.map(cevabiTasi),
                  })),
              }
            : {}),
        // T3b: gösterim koşulundaki cevap.<eski>.
        ...(p.gosterKosullar
            ? { gosterKosullar: p.gosterKosullar.map(cevabiTasi) }
            : {}),
        // T3c: koşullu değerlerdeki cevap.<eski> (ekranda ve blokta).
        ...(p.degerler
            ? {
                  degerler: p.degerler.map((k) => ({
                      ...k,
                      kosullar: k.kosullar.map(cevabiTasi),
                  })),
              }
            : {}),
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
