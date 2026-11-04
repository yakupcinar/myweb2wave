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
