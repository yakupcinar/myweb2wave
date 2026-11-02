# Huni mobil görünüm 3–5 (2026-11-02, döngü)

Kalan maddeler (`huni-mobil-gorunum.md`): 3 seçenek simgesi · 4 "planın hazırlanıyor" adımı · 5 başarı/iptal sayfaları. Kullanıcı 2026-11-02: "benden karar bekleme" — kararlar aşağıda, onay Ç78'de.

## 3 · Seçenek simgesi (emoji)
- **Karar:** seçeneğe isteğe bağlı `simge` — **tek grafem ve emoji** (`grapheme_strlen = 1` + PCRE `\p{Extended_Pictographic}|\p{Regional_Indicator}`; ≤ 32 bayt). ZWJ aile, ten tonu, bayrak kabul; harf, rakam, iki emoji, emoji + metin red. Görselle birlikte olmaz (aynı yeri doldurur). Metnin önünde, `aria-hidden` — anlam metinde, ekran okuyucu okumaz. Puck'ta seçenek dizisinde "Simge (tek emoji)". AI yönergesi bir cümle.
- **Ölçülen sınırlar:** ICU 72'de `IntlChar` emoji sabitleri yok (PCRE sınıfı kullanıldı). Tuş emojisi (1️⃣) PCRE'de emoji sayılmıyor → reddedilir (bilinçli kabul).
- **Gerçek çekimde iki kusur bulundu ve düzeltildi:**
  1. **AI'ın gözü emoji görmüyordu:** ekran kabında yalnız DejaVu — emojiler kutu (Ç76'nın emoji yüzü). `ekran/Dockerfile`: taban imaj + `fonts-noto-color-emoji` 2.051-0+deb13u1 (Debian, OFL-1.1, 11 MB); compose `ekran` artık bu imajdan. Sonra çekimde renkli emojiler doğru.
  2. **Bölünen birleşik emoji metnin üstüne taşıyordu:** emoji desteği zayıf ortamda 👨‍👩‍👧 üç karaktere bölündü, sabit 28 px genişliği aştı → `min-width: 28px` (simge büyüyebilir, metni iter, üstüne binmez). Esneme kuralı simgeyi hariç tutar.
- Testler: `HuniTest` (5 geçerli simge türü, 5 hata durumu) · `bilesenler.test.tsx` (simge önce, gizli, yalnız simgeli seçenekte; CSS: boyut, en az genişlik, esnemez) · `donustur.test.ts` (gidiş-dönüş).
- Kırma 8/8: K1 alan kabul edilmez · K2 grafem sınırı yok (iki emoji geçer) · K3 emoji denetimi yok (harf geçer) · K4 görselle birlikte geçer · K5 ekran okuyucuya açık · **K6 esneme kuralı simgeyi kapsar — ilk denemede tutmadı (test yoktu) → bekçi eklendi, K6b kırmızı** · K7 sabit genişlik · K8 Puck simgeyi okumaz.
- Gerçek sistem: KeepChat taslağı yedeklendi → simgeler (👨‍👩‍👧 🧑‍🤝‍🧑 💼 🤔) → çekim → **aynen geri yüklendi** (tanım + imza eşit). `bin/kontrol` yeşil (305 çekirdek testi).
- CI (madde 3): koşu 41398054165, SHA ce65901, `KONTROL: YEŞİL`, 305 test.

## 4 · "Planın hazırlanıyor" yükleme adımı
- **Karar:** yeni adım türü `yukleme` — `baslik` (zorunlu), `maddeler` (0–5, her biri 1–80), `sure` (2–10 sn, yoksa 4). Düğmesiz; çubuk süre boyunca dolar, maddeler sırayla belirir (CSS). Sonraki adıma geçişi **oynatıcı** yapar (zamanlayıcı; adım değişince — geri tuşu — iptal). Ekran çekiminde (`ekran=1`) geçmez. Ödeme adımı zaten en sonda zorunlu → yükleme son adım olamaz (ek kural gerekmedi). `aria-busy`; hareket azaltmada animasyon yok, geçiş yine olur.
- **Puck:** "Plan hazırlanıyor (yükleme)" bloğu (maddeler dizisi, süre); önizleme beklemez. **Dönüştürücüde tuzak:** bilinmeyen tür `default` dalında sessizce **ödeme** bloğuna eşleniyordu — `yukleme` dalı eklendi, test "ödemeye dönüşmez".
- **AI:** panel asistanı ve MCP talimatı yeni türü sayar (kural 7: iki istem birlikte, testli).
- **Ekran çekicisi:** ilk çekim animasyonun ilk karesiydi (çubuk boş, maddeler soluk) → `ChromeEkranCekici` artık `prefers-reduced-motion: reduce` öykünür: huni animasyonları kapatır, AI son ve durağan hâli görür (G3'teki ara kare sorununun genel çözümü). Otomatik testi yok — gerçek çekimle ölçüldü.
- Var olan bekçi doğru bir şeyi yakaladı: "her animasyon tek `prefers-reduced-motion: no-preference` bloğunda" — yeni animasyonlar o bloğa taşındı.
- Testler: `HuniTest` (geçerli + sözleşme, 7 hata durumu, tür hata metni) · `bilesenler.test.tsx` (çizim, varsayılanlar, `Adim`, CSS) · `Huni.test.tsx` (süre dolunca geçer + bildirir, geri dönünce iptal, ekran çekiminde geçmez — gerçek zamanlayıcı, sure 2) · `donustur.test.ts` · `PanelAiTest`/`McpTest` (istemler).
- Kırma 10/10: K1 tür tanınmaz · K2 süre sınırı yok · K3 madde sınırı yok · K4 varsayılan süre · K5 `Adim` çizmez · K6 zamanlayıcı ilerlemez · K7 iptal yok (geri dönünce yine geçer) · K8 ekran çekiminde geçer · K9 dönüştürücü ödemeye eşler · K10 çubuk animasyonu yok.
- Gerçek sistem (taslak yedeklendi → **aynen geri yüklendi**): ödemeden önce yükleme (3 sn, 3 madde); başsız Chrome'da ekran işareti olmadan **3,04 sn'de** ödeme adımına geçti; çekim (hareket azaltmalı) son hâli gösteriyor.
- `bin/kontrol` yeşil (313 çekirdek testi).
- CI (madde 4): koşu 41399603733, SHA 9b5c1b3, `KONTROL: YEŞİL`, 313 test.

## 5 · Başarı / iptal sayfaları huni görünümünde
- **Önce:** iki sayfa da stilsizdi (tarayıcı varsayılanı).
- **Karar:** `body.huni > main > section`, `huni.css`; ana bağlantı huninin ana düğmesi gibi (`.huni a.ana`, `button.ana` kuralıyla ortak — G4 biçimi dahil). **Tema taşıma:** Stripe dönüş adresinde huni bilgisi yok → oynatıcı temayı **sekmenin oturum belleğine** yazar (`lib/sonuc-tema.ts`, `huni-tema`; temasız huni kaydı siler), sonuç sayfası okur ve köke uygular. Sunucu/adres değişmedi; bozuk kayıt ya da kapalı depolama (gizli sekme) → varsayılan görünüm, sayfa düşmez. İptal sayfasına kısa açıklama ("Ücret alınmadı…"). Başarı sayfasının mantığı (giriş bağlantısı, piksel, kimlik temizliği) değişmedi.
- Reddedilen: dönüş adresine huni/tema parametresi (Stripe adresi sunucu yapılandırması, açık yönlendirme riski; adres değişmesin) · sunucudan son huninin teması (ziyaretçi ↔ huni eşlemesi gerekir; oturum belleği yeterli).
- Testler: `sonuc-tema.test.ts` (kaydet → uygula, temasız siler, bozuk/kapalı depolama düşürmez; iki sayfanın kaynağı `.huni` + `huni.css` + `temaUygula`) · `Huni.test.tsx` (oynatıcı temayı kaydeder) · `bilesenler.test.tsx` (ana düğme seçici listesi).
- Kırma 6/6: K1 oynatıcı kaydetmez · K2 uygulama köke yazmaz · **K3 ilk yama sözdizimini bozdu (derleme kırmızısı, sahte §8) → K3c hatayı yeniden fırlatarak: kırmızı** · K4 temasız kayıt silinmez · K5 iptal gövdesi `.huni` değil · K6 bağlantı ana düğme kuralı dışında. Not: K2–K4'ün ilk yamaları Prettier girintisi yüzünden tutmadı (KOŞULMADI), yeniden koşuldu.
- Gerçek tarayıcı (ekran kabı, oturum belleğine tema yazılıp açıldı): iptal ve başarı sayfaları geçiş zemini, Nunito, büyük başlık, hap düğmeyle; tema yoksa varsayılan (`--ana` #db2777 / #2563eb).
- `bin/kontrol` yeşil (313 çekirdek testi; huni 70, bileşen 59).
- **Ölçülmedi:** tema modül betiğiyle (sayfa ayrıştırıldıktan sonra) uygulanıyor — çok yavaş cihazda kısa varsayılan-renk parlaması olabilir (§40 benzeri); gerçek iPhone'da ölçülmedi (Ç79).
