# P7h — Özel çizilmiş onay kutusu (2026-11-11)

Kullanıcı: "tiklenen şeyler çok modern durmuyor, nasıl yapıyorlar araştır". Karar (A27): **özel çizilmiş kutu**.

## Ne yapıldı

Paneldeki onay kutuları (Webhook olayları ve "Tümü", API anahtarı izni) ortak `.kutu` çizimine geçti
([`stil.css`](../../core/panel/src/uygulama/stil.css), `kutuSinifi`):

- 18 px kare, 5 px köşe, `kenar-2` çerçeve;
- seçili hâl vurgu dolu ve tikli; yarım ("Tümü" kısmen seçili) hâl vurgu dolu ve çizgili;
- üzerine gelince çerçeve koyulaşır; odakta vurgu halkası; devre dışıyken soluk.

Tikin rengi temadan gelir (`--kutu-tik`, `--kutu-cizgi`): açıkta beyaz, koyuda siyah. Koyu temada vurgunun
üstündeki yazı siyah, bu yüzden beyaz tik orada okunmazdı. Windows yüksek karşıtlık kipinde tarayıcının kendi
kutusuna dönülür. Huni düzenleyicisindeki kutu Puck'ın kendi alanında, ona dokunulmadı.

## Neden böyle

Araştırma:

- Chrome ve MDN form denetimi rehberleri iki yol tarif ediyor: `accent-color` (yalnız renk) ve `appearance: none`
  (tam çizim, durumlar elle yazılır).
- Apple HIG çoklu seçimde onay kutusu öneriyor; alt öğeleri karışık grupta "karışık" durum gösterilmeli.

Bizdeki `accent-color` yalnız rengi değiştiriyordu; şekil ve kalınlık tarayıcıya kalıyordu. Gerçek `<input>` yerinde
kaldığı için klavye, form gönderimi ve ekran okuyucu değişmedi.

Reddedilenler:

- **Webhook olayları için hap (chip):** daha gösterişli, ama Apple dilinde çoklu seçim listesi kutuyla yapılıyor.
- **Anahtar (switch):** HIG ve yaygın kural, anahtarın hemen uygulanan ayarlar için olduğunu söylüyor. Bizim
  kutularımız Kaydet / Ekle ile birlikte gönderiliyor.

## Kırma ve ölçüm

- **H1** kutu sınıfı eskiye (`accent-vurgu`): ortak alan denetimi 3 sayfa testinde düştü (Webhook'lar, Anahtarlar,
  Bağlam; üçü de Entegrasyonlar'ı çiziyor).
- Görsel durumlar (boş, seçili, yarım; açık ve koyu) ekran döngüsünün yeni `entegrasyonlar-kutu` durumuyla görüldü.
  Vitest'te CSS olmadığı için çizimin kendisi birim testle ölçülemez (§32).
- **Tam kontrol:** YEŞİL; 748 çekirdek testi, 184 panel testi.
