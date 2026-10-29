// Ç41: panel oturumu düşünce (8 sa boşta) JSON uçları giriş sayfasına
// yönlendirir; fetch yönlendirmeyi izleyip HTML döner. Bunu ayırt eden tek
// yer — düzenleyici kaydedilmemiş işi korur ve açık mesaj verir.
// Panelin JSON uçları başka hiçbir yere yönlendirmez (ölçüldü: girişsiz POST
// → 302 /panel/giris).
export function oturumDustu(y: Response): boolean {
    return y.redirected;
}

export const OTURUM_DUSTU =
    "Oturumun süresi doldu — değişikliklerin bu sekmede duruyor. Yeni sekmede panele giriş yap, sonra burada yeniden Kaydet'e bas.";
