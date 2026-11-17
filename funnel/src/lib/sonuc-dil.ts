// M9d2b: Stripe'tan dönen başarı / iptal sayfası ve abonelik sayfası huninin
// dilinde. Dil, tema gibi oturum belleğinde taşınır (sonuc-tema.ts); oradan
// yoksa (e-postadaki bağlantıyla gelen) ?dil=, sonra tarayıcının dili. Sabit
// HTML metinleri data-metin ile işaretli; JS çalışmazsa Türkçe kalır.
import {
  dilOku,
  METINLER,
  type Dil,
  type Metinler,
} from "../../../huni-bilesenleri/metinler";

const ANAHTAR = "huni-dil";

/** Oynatıcı tanım gelince çağırır. */
export function dilKaydet(dil: Dil): void {
  try {
    sessionStorage.setItem(ANAHTAR, dil);
  } catch {
    // Depolama kapalı: sonuç sayfası adresten ya da tarayıcıdan tahmin eder.
  }
}

/** Sonuç sayfasının dili: oturum belleği → ?dil= → tarayıcı. */
export function sonucDili(): Dil {
  let kayitli: string | null = null;
  try {
    kayitli = sessionStorage.getItem(ANAHTAR);
  } catch {
    // depolama kapalı
  }
  return dilOku(
    kayitli ??
      new URLSearchParams(window.location.search).get("dil") ??
      navigator.language.slice(0, 2),
  );
}

/** data-metin işaretli öğelere sözlükten yazar; sayfa dilini ve başlığı ayarlar. */
export function metinleriUygula(dil: Dil): Metinler {
  const m = METINLER[dil];
  document.documentElement.lang = dil;
  for (const el of document.querySelectorAll<HTMLElement>("[data-metin]")) {
    const deger = m[el.dataset["metin"] as keyof Metinler];
    if (typeof deger === "string") el.textContent = deger;
  }
  return m;
}
