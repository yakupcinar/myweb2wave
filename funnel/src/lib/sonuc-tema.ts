// Mobil görünüm 5: Stripe'tan dönen başarı/iptal sayfası huninin temasıyla
// çizilsin. Tema oturum belleğinde (sekme kapanınca silinir) taşınır —
// dönüş adresine ve sunucuya eklenmez. Değerler çekirdekte doğrulanmıştı;
// köke yine setProperty ile yazılır. Depolama kapalıysa (gizli sekme)
// varsayılan görünüm.
import { temaDegiskenleri, type Tema } from "../../../huni-bilesenleri/tema";

const ANAHTAR = "huni-tema";

/** Oynatıcı tanım gelince çağırır; temasız huni önceki kaydı siler. */
export function temaKaydet(tema: Tema | undefined): void {
  try {
    if (tema) sessionStorage.setItem(ANAHTAR, JSON.stringify(tema));
    else sessionStorage.removeItem(ANAHTAR);
  } catch {
    // Depolama kapalı: sonuç sayfası varsayılan temayla çizilir.
  }
}

/** Sonuç sayfası yüklenirken çağırır. */
export function temaUygula(medyaKoku: string): void {
  let tema: Tema | undefined;
  try {
    tema = JSON.parse(sessionStorage.getItem(ANAHTAR) ?? "null") ?? undefined;
  } catch {
    return;
  }
  if (!tema) return;
  const kok = document.documentElement.style;
  for (const [ad, deger] of Object.entries(temaDegiskenleri(tema, medyaKoku)))
    kok.setProperty(ad, deger);
}
