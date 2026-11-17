// Ç19/Ç21: paywall fiyatı Stripe fiyat nesnesinden (çekirdek huni yanıtında
// taşır); elle yazılan "9,99 €" metni Stripe'tan sessizce ayrışırdı.
// M1b: teklif metnin başında, normal fiyat "sonra" ile — ödeme sayfasında
// çekilecek her tutar paywall'da yazılı olsun. İndirimli tutar HESAPLANMAZ
// (Stripe'ın yüzde yuvarlamasıyla bir kuruş ayrışabilir); yüzde yazılır.
// Bölünmez boşluk (B): tutar birimiyle kalır, metin yalnız virgülden kırılır —
// yoksa "/ ay" alt satıra tek başına düşüyordu (paywall ekranında ölçüldü).
// M9d2: cümleler ve sayı biçimi huninin dilinde ("€9,99" / "€9.99").
import type { Dil } from "../../../huni-bilesenleri/metinler";
import type { components } from "../sozlesme/api";

type PlanFiyati = components["schemas"]["PlanFiyati"];
type Aralik = PlanFiyati["aralik"];

const B = " ";

const YEREL: Record<Dil, string> = { tr: "tr-TR", en: "en-US" };

const ARALIK: Record<Dil, Record<Aralik, string>> = {
  tr: { day: "gün", week: "hafta", month: "ay", year: "yıl" },
  en: { day: "day", week: "week", month: "month", year: "year" },
};

// "her ay" bölünmez: koşul satırı "her / ay" diye kırılıyordu (ekranda ölçüldü).
const HER: Record<Dil, Record<Aralik, string>> = {
  tr: {
    day: `her${B}gün`,
    week: `her${B}hafta`,
    month: `her${B}ay`,
    year: `her${B}yıl`,
  },
  en: {
    day: `every${B}day`,
    week: `every${B}week`,
    month: `every${B}month`,
    year: `every${B}year`,
  },
};

const gunler = (gun: number, dil: Dil): string =>
  dil === "en" ? `${gun}${B}${gun === 1 ? "day" : "days"}` : `${gun}${B}gün`;

function paraYazici(paraBirimi: string, dil: Dil): (tutar: number) => string {
  const bicim = new Intl.NumberFormat(YEREL[dil], {
    style: "currency",
    currency: paraBirimi,
  });
  return (tutar) => bicim.format(tutar);
}

export function fiyatMetni(
  fiyat: PlanFiyati | undefined,
  dil: Dil = "tr",
): string | undefined {
  if (!fiyat) return undefined;
  const para = paraYazici(fiyat.paraBirimi, dil);
  const aralik = ARALIK[dil][fiyat.aralik];
  const normal = `${para(fiyat.tutar)}${B}/${B}${aralik}`;
  const t = fiyat.teklif;
  if (!t) return normal;
  if (dil === "en") {
    if (t.tur === "deneme")
      return `${gunler(t.gun, dil)} free, then${B}${normal}`;
    if (t.tur === "giris")
      return `${gunler(t.gun, dil)}${B}${para(t.tutar)}, then${B}${normal}`;
    return `First${B}${aralik} ${t.yuzde}%${B}off, then${B}${normal}`;
  }
  if (t.tur === "deneme")
    return `${gunler(t.gun, dil)} ücretsiz, sonra${B}${normal}`;
  if (t.tur === "giris")
    return `${gunler(t.gun, dil)}${B}${para(t.tutar)}, sonra${B}${normal}`;
  return `İlk${B}${aralik} %${t.yuzde}${B}indirimli, sonra${B}${normal}`;
}

// M2a: seçili planın ödeme koşulu — düğmenin altında, yalnız tahsilat olguları
// (ne zaman, ne kadar, hangi aralıkla). İptal sözü yok: iptal yolu M6'da.
export function kosulMetni(
  fiyat: PlanFiyati | undefined,
  dil: Dil = "tr",
): string | undefined {
  if (!fiyat) return undefined;
  const para = paraYazici(fiyat.paraBirimi, dil);
  const aralik = ARALIK[dil][fiyat.aralik];
  const t = fiyat.teklif;
  if (dil === "en") {
    const yenileme = `the subscription renews ${HER[dil][fiyat.aralik]} at ${para(fiyat.tutar)}.`;
    if (!t) return `${para(fiyat.tutar)} today; ${yenileme}`;
    if (t.tur === "deneme")
      return `Nothing to pay today. First payment in ${gunler(t.gun, dil)}; ${yenileme}`;
    if (t.tur === "giris")
      return `${para(t.tutar)} today. First payment in ${gunler(t.gun, dil)}; ${yenileme}`;
    return `First${B}${aralik} ${t.yuzde}%${B}off; then ${yenileme}`;
  }
  const yenileme = `abonelik ${HER[dil][fiyat.aralik]} ${para(fiyat.tutar)} ile yenilenir.`;
  if (!t) return `Bugün ${para(fiyat.tutar)} ödenir; ${yenileme}`;
  if (t.tur === "deneme")
    return `Bugün ödeme yok. ${gunler(t.gun, dil)} sonra ilk ödeme alınır; ${yenileme}`;
  if (t.tur === "giris")
    return `Bugün ${para(t.tutar)} ödenir. ${gunler(t.gun, dil)} sonra ilk ödeme alınır; ${yenileme}`;
  return `İlk${B}${aralik} %${t.yuzde}${B}indirimli ödenir; sonra ${yenileme}`;
}

// M3c: tek seferlik tutar (ek ürün) — fiyat metniyle aynı biçim.
export function tutarMetni(
  tutar: number,
  paraBirimi: string,
  dil: Dil = "tr",
): string {
  return paraYazici(paraBirimi, dil)(tutar);
}
