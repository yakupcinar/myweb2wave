// KeepChat hunisi — TASLAK METİN (kullanıcı düzeltecek, 2026-10-20).
// Yalnız KeepChat'in bugün yaptığı şeyler yazıldı: Pro hiçbir özelliğin
// kilidini açmıyor (A5 kullanıcı kararı), o yüzden özellik vaadi yok.
// Kimlikleri değiştirmek paneldeki eski verilerle eşleşmeyi koparır;
// metni değiştirmek serbest.
import type { Huni } from "./tipler";

export const keepchat: Huni = {
  app: "keepchat",
  sayfaBasligi: "KeepChat Pro",
  adimlar: [
    {
      id: "karsilama",
      tur: "tanitim",
      baslik: "KeepChat'e hoş geldin",
      metin:
        "Sade, birebir mesajlaşma — tarayıcıdan ve iPhone'dan. Seni biraz tanıyalım: üç kısa soru.",
      dugme: "Başlayalım",
    },
    {
      id: "kiminle",
      tur: "soru",
      soru: "KeepChat'i en çok kiminle kullanacaksın?",
      secenekler: [
        { id: "aile", metin: "Ailemle" },
        { id: "arkadas", metin: "Arkadaşlarımla" },
        { id: "is", metin: "İş için" },
        { id: "bilmiyorum", metin: "Henüz bilmiyorum" },
      ],
    },
    {
      id: "cihaz",
      tur: "soru",
      soru: "Nereden yazışırsın?",
      secenekler: [
        { id: "iphone", metin: "iPhone'dan" },
        { id: "tarayici", metin: "Bilgisayardan (tarayıcı)" },
        { id: "ikisi", metin: "İkisinden de" },
      ],
    },
    {
      id: "oncelik",
      tur: "soru",
      soru: "Bir mesajlaşma uygulamasında senin için en önemlisi ne?",
      secenekler: [
        { id: "sadelik", metin: "Sadelik" },
        { id: "hiz", metin: "Hız" },
        { id: "gizlilik", metin: "Gizlilik" },
        { id: "diger", metin: "Başka bir şey" },
      ],
    },
    {
      id: "hazir",
      tur: "tanitim",
      baslik: "Teşekkürler!",
      metin:
        "KeepChat Pro aboneliğiyle uygulamanın geliştirilmesini destekleyebilirsin. Ödemeden sonra uygulamada Ayarlar → Abonelik → Aboneliğimi bağla ile hesabına bağlarsın.",
      dugme: "Planı gör",
    },
    { id: "paywall", tur: "paywall" },
  ],
};
