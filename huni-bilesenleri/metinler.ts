// M9d2: huninin SABİT metinleri (oynatıcı + ortak bileşenler) iki dilde.
// Huninin kendi içeriğini (başlık, soru, paywall) yazar yazar; burada yalnız
// kodun koyduğu metinler. Dil huni tanımında (`dil`, yoksa tr); diller yasal
// metinlerinkiyle aynı (çekirdek Taslaklar::DILLER). `en`, `tr` ile aynı
// anahtarları taşımak ZORUNDA (tip): eksik çeviri derlemeyi kırar.
import { createContext, useContext } from "react";

export type Dil = "tr" | "en";

const tr = {
    yukleniyor: "Yükleniyor…",
    sayfaYuklenemedi: "Sayfa yüklenemedi. Lütfen yenileyin.",
    onizlemeAcilamadi:
        "Önizleme açılamadı: bağlantının süresi dolmuş olabilir.",
    odemeSaglayicisi:
        "Ödeme sağlayıcısına şu an ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.",
    birSeylerTers: "Bir şeyler ters gitti. Lütfen tekrar deneyin.",
    baglantiKurulamadi:
        "Bağlantı kurulamadı. İnternetinizi kontrol edip tekrar deneyin.",
    izinIletilemedi:
        "İzin tercihiniz sunucuya iletilemedi. Lütfen tekrar deneyin.",
    geri: "Geri",
    gizlilikAyarlari: "Gizlilik ayarları",
    izinSeridi: "Reklam ölçümü izni",
    izinMetni:
        "Reklamlarımızın işe yarayıp yaramadığını ölçmek için Meta ve TikTok'un ölçüm araçlarını kullanmak istiyoruz. İzin verirsen hangi reklamdan geldiğin ve abone olursan bu satın alma onlarla paylaşılır. İzin vermesen de her şey aynı çalışır.",
    cerezBildirimi: "Çerez ve izleme bildirimi",
    gizlilikPolitikasi: "Gizlilik politikası",
    kullanimSartlari: "Kullanım şartları",
    iptalIade: "İptal ve iade",
    reddet: "Reddet",
    kabulEt: "Kabul et",
    tercihKaydediliyor: "Tercihiniz kaydediliyor…",
    odeme: "Ödeme",
    kapat: "Kapat",
    odemeFormuAcilamadi: "Ödeme formu açılamadı. Lütfen tekrar deneyin.",
    teklif: "Teklif",
    hayirTesekkurler: "Hayır, teşekkürler",
    sartlariniBaglanti: "Kullanım şartlarını",
    sartlarOnay: "ve abonelik koşulunu okudum, kabul ediyorum.",
    epostaAdresiniz: "E-posta adresiniz",
    yanitiniz: "Yanıtınız",
    istegeBagli: " (isteğe bağlı)",
    puan: (puan: number) => `5 üzerinden ${puan} puan`,
    guvenliOdeme: "Güvenli ödeme · Stripe",
    kartSaklanmaz: "Kart bilgin bizde saklanmaz",
    teklifBitmesine: "Teklifin bitmesine",
    onerilen: "Önerilen",
    kalanGun: (gun: number) => `${gun} gün`,
    // M9d2b: ödeme sonrası sayfalar (başarılı, iptal, abonelik) ve ek ürün.
    odemeTamamlanmadi: "Ödeme tamamlanmadı",
    ucretAlinmadi: "Ücret alınmadı. Planına istediğin zaman geri dönebilirsin.",
    planaGeriDon: "Plana geri dön",
    tesekkurler: "Teşekkürler",
    tesekkurlerBaslik: "Teşekkürler!",
    aboneligBaslatiliyor: "Aboneliğiniz başlatılıyor…",
    odemenizAlindi: "Ödemeniz alındı.",
    kurulumMetni:
        "KeepChat telefonunuzda kurulu değilse önce App Store'dan kurun, sonra bu sayfaya dönüp bağlantıya yeniden dokunun. Aynı bağlantıyı e-postanıza da gönderdik; tek kullanımlıktır, 24 saat geçerlidir.",
    yedekMetni:
        "Giriş bağlantısını e-postanıza gönderdik. Bulamazsanız KeepChat'te Ayarlar → Abonelik → Aboneliğimi bağla'ya gidip ödemede yazdığınız e-postayı girin.",
    uygulamayiAc: "KeepChat'i aç ve giriş yap",
    aboneligimiYonet: "Aboneliğimi yönet",
    portalAciklama:
        "Abone olduğun e-posta adresini yaz; kartını güncellemen, aboneliğini iptal etmen ve faturalarını görmen için bir bağlantı gönderelim.",
    eposta: "E-posta",
    baglantiGonder: "Bağlantı gönder",
    portalGonderildi:
        "Bu adrese bağlı bir abonelik varsa bağlantıyı gönderdik. E-postanı kontrol et (1 saat geçerli).",
    portalEpostaKontrol: "E-posta adresini kontrol edip yeniden dene.",
    portalGonderilemedi: "Şu an gönderilemedi, birazdan yeniden dene.",
    kartReddetti: "Kartınız bu ödemeyi kabul etmedi.",
    teklifSuresiDoldu: "Bu teklifin süresi doldu.",
    teklifKullanilamiyor: "Bu teklif şu an kullanılamıyor.",
    bankaDogrulamasi: "Banka doğrulaması tamamlanmadı.",
    eklendiOdendi: "Eklendi — ödemeniz alındı.",
    tekSeferlik: "Tek seferlik ödeme; abonelikte kullandığın kartından alınır.",
};

export type Metinler = typeof tr;

const en: Metinler = {
    yukleniyor: "Loading…",
    sayfaYuklenemedi: "The page couldn't load. Please refresh.",
    onizlemeAcilamadi: "Preview couldn't open: the link may have expired.",
    odemeSaglayicisi:
        "The payment provider can't be reached right now. Please try again shortly.",
    birSeylerTers: "Something went wrong. Please try again.",
    baglantiKurulamadi:
        "Couldn't connect. Check your internet connection and try again.",
    izinIletilemedi: "Your choice couldn't be saved. Please try again.",
    geri: "Back",
    gizlilikAyarlari: "Privacy settings",
    izinSeridi: "Ad measurement consent",
    izinMetni:
        "We'd like to use Meta's and TikTok's measurement tools to see whether our ads work. If you allow it, which ad you came from and, if you subscribe, that purchase are shared with them. Everything works the same if you don't.",
    cerezBildirimi: "Cookie and tracking notice",
    gizlilikPolitikasi: "Privacy policy",
    kullanimSartlari: "Terms of use",
    iptalIade: "Cancellation and refunds",
    reddet: "Decline",
    kabulEt: "Accept",
    tercihKaydediliyor: "Saving your choice…",
    odeme: "Payment",
    kapat: "Close",
    odemeFormuAcilamadi: "The payment form couldn't open. Please try again.",
    teklif: "Offer",
    hayirTesekkurler: "No, thanks",
    sartlariniBaglanti: "I have read and accept the terms of use",
    sartlarOnay: "and the subscription conditions.",
    epostaAdresiniz: "Your email address",
    yanitiniz: "Your answer",
    istegeBagli: " (optional)",
    puan: (puan: number) => `${puan} out of 5`,
    guvenliOdeme: "Secure payment · Stripe",
    kartSaklanmaz: "We never store your card details",
    teklifBitmesine: "Offer ends in",
    onerilen: "Recommended",
    kalanGun: (gun: number) => (gun === 1 ? "1 day" : `${gun} days`),
    odemeTamamlanmadi: "Payment not completed",
    ucretAlinmadi:
        "You haven't been charged. You can return to your plan anytime.",
    planaGeriDon: "Back to the plan",
    tesekkurler: "Thank you",
    tesekkurlerBaslik: "Thank you!",
    aboneligBaslatiliyor: "Starting your subscription…",
    odemenizAlindi: "Payment received.",
    kurulumMetni:
        "If KeepChat isn't installed on your phone, install it from the App Store first, then come back to this page and tap the link again. We also emailed you the same link; it works once and is valid for 24 hours.",
    // Ç138: KeepChat'in İngilizce menü adları doğrulanmadı — yol adsız anlatılır.
    yedekMetni:
        "We emailed you a sign-in link. If you can't find it, open KeepChat, go to the subscription section in settings and enter the email you used at checkout.",
    uygulamayiAc: "Open KeepChat and sign in",
    aboneligimiYonet: "Manage my subscription",
    portalAciklama:
        "Enter the email you subscribed with and we'll send you a link to update your card, cancel your subscription or see your invoices.",
    eposta: "Email",
    baglantiGonder: "Send link",
    portalGonderildi:
        "If a subscription is linked to this address, we've sent the link. Check your email (valid for 1 hour).",
    portalEpostaKontrol: "Check the email address and try again.",
    portalGonderilemedi: "Couldn't send right now, please try again shortly.",
    kartReddetti: "Your card declined this payment.",
    teklifSuresiDoldu: "This offer has expired.",
    teklifKullanilamiyor: "This offer isn't available right now.",
    bankaDogrulamasi: "Bank verification wasn't completed.",
    eklendiOdendi: "Added — payment received.",
    tekSeferlik: "One-time payment, charged to the card you subscribed with.",
};

export const METINLER: Record<Dil, Metinler> = { tr, en };

/** Tanımdaki dil; bilinmeyen ya da yoksa tr (çekirdek zaten doğrular). */
export function dilOku(deger: unknown): Dil {
    return deger === "en" ? "en" : "tr";
}

export const DilBaglami = createContext<Dil>("tr");

export function useDil(): Dil {
    return useContext(DilBaglami);
}

export function useMetinler(): Metinler {
    return METINLER[useContext(DilBaglami)];
}
