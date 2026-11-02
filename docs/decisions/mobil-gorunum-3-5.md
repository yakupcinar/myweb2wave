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
