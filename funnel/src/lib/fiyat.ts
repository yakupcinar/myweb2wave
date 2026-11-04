// Ç19/Ç21: paywall fiyatı Stripe fiyat nesnesinden (çekirdek huni yanıtında
// taşır); elle yazılan "9,99 €" metni Stripe'tan sessizce ayrışırdı.
// M1b: teklif metnin başında, normal fiyat "sonra" ile — ödeme sayfasında
// çekilecek her tutar paywall'da yazılı olsun. İndirimli tutar HESAPLANMAZ
// (Stripe'ın yüzde yuvarlamasıyla bir kuruş ayrışabilir); yüzde yazılır.
// Bölünmez boşluk (B): tutar birimiyle kalır, metin yalnız virgülden kırılır —
// yoksa "/ ay" alt satıra tek başına düşüyordu (paywall ekranında ölçüldü).
import type { components } from "../sozlesme/api";

type PlanFiyati = components["schemas"]["PlanFiyati"];

const B = "\u00a0";

const ARALIK: Record<PlanFiyati["aralik"], string> = {
  day: "gün",
  week: "hafta",
  month: "ay",
  year: "yıl",
};

export function fiyatMetni(fiyat: PlanFiyati | undefined): string | undefined {
  if (!fiyat) return undefined;
  const para = (tutar: number) =>
    new Intl.NumberFormat("tr-TR", {
      style: "currency",
      currency: fiyat.paraBirimi,
    }).format(tutar);
  const normal = `${para(fiyat.tutar)}${B}/${B}${ARALIK[fiyat.aralik]}`;
  const t = fiyat.teklif;
  if (!t) return normal;
  if (t.tur === "deneme") return `${t.gun}${B}gün ücretsiz, sonra${B}${normal}`;
  if (t.tur === "giris")
    return `${t.gun}${B}gün${B}${para(t.tutar)}, sonra${B}${normal}`;
  return `İlk${B}${ARALIK[fiyat.aralik]} %${t.yuzde}${B}indirimli, sonra${B}${normal}`;
}

// "her ay" bölünmez: koşul satırı "her / ay" diye kırılıyordu (ekranda ölçüldü).
const HER: Record<PlanFiyati["aralik"], string> = {
  day: `her${B}gün`,
  week: `her${B}hafta`,
  month: `her${B}ay`,
  year: `her${B}yıl`,
};

// M2a: seçili planın ödeme koşulu — düğmenin altında, yalnız tahsilat olguları
// (ne zaman, ne kadar, hangi aralıkla). İptal sözü yok: iptal yolu M6'da.
export function kosulMetni(fiyat: PlanFiyati | undefined): string | undefined {
  if (!fiyat) return undefined;
  const para = (tutar: number) =>
    new Intl.NumberFormat("tr-TR", {
      style: "currency",
      currency: fiyat.paraBirimi,
    }).format(tutar);
  const yenileme = `abonelik ${HER[fiyat.aralik]} ${para(fiyat.tutar)} ile yenilenir.`;
  const t = fiyat.teklif;
  if (!t) return `Bugün ${para(fiyat.tutar)} ödenir; ${yenileme}`;
  if (t.tur === "deneme")
    return `Bugün ödeme yok. ${t.gun}${B}gün sonra ilk ödeme alınır; ${yenileme}`;
  if (t.tur === "giris")
    return `Bugün ${para(t.tutar)} ödenir. ${t.gun}${B}gün sonra ilk ödeme alınır; ${yenileme}`;
  return `İlk${B}${ARALIK[fiyat.aralik]} %${t.yuzde}${B}indirimli ödenir; sonra ${yenileme}`;
}

// M3c: tek seferlik tutar (ek ürün) — fiyat metniyle aynı biçim.
export function tutarMetni(tutar: number, paraBirimi: string): string {
  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: paraBirimi,
  }).format(tutar);
}
