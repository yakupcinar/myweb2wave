// Reklam ölçümü izni (H6a, A12): ziyaretçinin seçimi bu tarayıcıda
// hatırlanır — yalnız seçimin kendisi (kişisel veri değil). Depo yoksa
// (gizli pencere, engelli site verisi) her açılışta şerit yeniden çıkar;
// hata vermez.
export type Izin = "evet" | "hayir";
const ANAHTAR = "huni-reklam-izni";

export function izinOku(): Izin | null {
  try {
    const deger = window.localStorage.getItem(ANAHTAR);
    return deger === "evet" || deger === "hayir" ? deger : null;
  } catch {
    return null;
  }
}

export function izinYaz(izin: Izin): void {
  try {
    window.localStorage.setItem(ANAHTAR, izin);
  } catch {
    // Depo yok: seçim yalnız bu sayfa açıkken geçerli.
  }
}
