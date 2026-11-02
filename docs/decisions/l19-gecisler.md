# L19 — yöne göre geçiş ve yumuşak ilerleme çubuğu (Ç54) (2026-11-02, döngü)

Ç54 (H4'ten kalanlar): (1) hareket azaltma tarayıcıda ölçülmemişti (araçta medya öykünmesi yoktu) (2) ileri/geri yönüne göre kayma yok (3) ilerleme çubuğu (`<progress>`) geçişsiz atlıyor.

## Karar
- **Yön:** oynatıcı geçiş durumu `boolean` yerine yön: `data-gecis="ileri" | "geri"` (ilk adımda yok — H4 kuralı korunur). İleri sağdan (`huni-adim-ileri`, +16px), geri soldan (`huni-adim-geri`, −16px), 220 ms. Yükleme adımının kendiliğinden geçişi de `ileri`.
- **Çubuk:** yerel `<progress>` geçiş desteklemiyor → ekran okuyucu için yerinde, görünmez (`.ilerleme` içinde mutlak, `opacity: 0`); görünen `.ilerleme-dolum` genişliği `(sıra+1)/adım` %, `transition: width 300ms`. Tüm animasyon/geçiş tek `prefers-reduced-motion: no-preference` bloğunda (bekçi test genişletildi: geçiş de blok dışında olamaz).
- Reddedilen: çıkış animasyonu (eski adımın kayarak çıkması — iki adımı aynı anda tutmak gerekir, karmaşıklık; giriş yeterli) · çubuğu JS ile kare kare değiştirmek (CSS geçişi yeter).

## Ölçüm
- Testler: `Huni.test.tsx` (ileri → `ileri`, geri tuşu → `geri`; dolgu %33,3 → %66,7; yapı testleri `.ilerleme` kabına göre güncellendi) · `tema.test.ts` (iki yön animasyonu ve dolgu geçişi blokta, blok dışında yok).
- Kırma 6/6: K1 geri de `ileri` · K2 ileri işaret yazmaz · K3 dolgu hep %100 · K4 ileri animasyonu yok · K5 dolgu geçişi yok · K6 yerel progress kaldırılır (erişilebilirlik).
- **Gerçek tarayıcı (ekran kabı, CDP medya öykünmesi — Ç54 (1) artık ölçülebiliyor):** normalde ileri `["geçiş:width","huni-adim-ileri"]`, geri `["geçiş:width","huni-adim-geri"]`; **hareket azaltmada `[]`** (hiç animasyon yok), yön işareti yine doğru.
- `bin/kontrol` yeşil (313 çekirdek testi; huni 58, bileşen 59; JS bütçesi geçti).
