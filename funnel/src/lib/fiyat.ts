// Ç19/Ç21: paywall fiyatı Stripe fiyat nesnesinden (çekirdek huni yanıtında
// taşır); elle yazılan "9,99 €" metni Stripe'tan sessizce ayrışırdı.
import type { components } from "../sozlesme/api";

type PlanFiyati = components["schemas"]["PlanFiyati"];

const ARALIK: Record<PlanFiyati["aralik"], string> = {
  day: "gün",
  week: "hafta",
  month: "ay",
  year: "yıl",
};

export function fiyatMetni(fiyat: PlanFiyati | undefined): string | undefined {
  if (!fiyat) return undefined;
  const tutar = new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: fiyat.paraBirimi,
  }).format(fiyat.tutar);
  return `${tutar} / ${ARALIK[fiyat.aralik]}`;
}
