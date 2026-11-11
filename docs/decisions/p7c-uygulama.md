# P7c — Uygulama sayfası taslakla bire bir (2026-11-11)

## Ne yapıldı

`/panel/ayarlar` taslaktaki üç bölüme geçti (880 px genişlik):

1. **Canlıya geçiş.** Altı adım, "6 adımdan 2'si tamam" sayacı ve ilerleme çubuğu. Tamamlanan adımda yeşil onay
   ikonu var, bağlantı yok. Eksik adımda "Nasıl yapılır" düğmesi var; açılınca hangi ortam değişkeninin yazılacağı
   görünür (değer gösterilmez). Yasal adımda "Aç" (`/panel/yasal`) var.
2. **Uygulama bilgileri.** Satırlar `ui/Alan` `Satir` ile kuruldu: etiket solda 220 px. Sıra taslaktaki gibi: ad,
   destek, App Store, şema. Şema alanının sağında `://giris` eki var. Kaydet düğmesi kartın altında, sağda.
3. **Yeni hunilerin görünümü.** Tek satırlık bir kart: `"genel" hunisinden · 10 Kasım`, "Tema ve paywall yeni açılan
   hunilere kopyalanır…" ve sağda Kaldır.

Adımlar elle işaretlenmez; çalışan sistemden ölçülür. Yeni uç `GET /panel/api/uygulamalar/{app}/canliya-gecis`
([`CanliyaGecis`](../../core/src/Uygulama/CanliyaGecis.php)):

| Adım | Tamam sayılması için |
|---|---|
| Ödeme: Stripe | `STRIPE_SECRET_KEY` dolu. Kip önekten okunur: `sk_live_` ya da `rk_live_` canlı, diğerleri test |
| Stripe bildirimleri | `STRIPE_WEBHOOK_SECRET` dolu **ve** en az bir olay işlenmiş (`processed_webhook_events`). Son olayın tarihi gösterilir |
| E-posta (Postmark) | `MAILER_DSN` `postmark+` ile başlıyor |
| Yasal metinler | Şartlar ve gizlilik **Türkçe** yayında; paywall bunları bağlar (HuniController `DIL`) |
| Meta reklam hesabı | `META_AD_ACCOUNT_ID` ve `META_INSIGHTS_ACCESS_TOKEN` dolu |
| Otomatik görevler | `CanliyaGecis::GOREVLER`'deki her komut, beklenen aralıkta **başarıyla** bitmiş |

Görev ölçümü için `zamanli_gorevler` tablosu eklendi (`komut`, `son_calisma`). Bu tabloyu
[`GorevKaydi`](../../core/src/Uygulama/GorevKaydi.php) yazar; konsolun bitiş olayını dinler ve yalnız listedeki
komut 0 koduyla bitince kayıt düşer.

## Neden böyle

- **Elle işaretlenen liste reddedildi.** "Yaptım" işareti ölçmeden iddia olurdu (kural 1). Cron silinse ya da anahtar
  değişse liste yine yeşil kalırdı.
- **Görev için kuyruk tablolarına bakmak reddedildi.** Yaşlı bekleyen kayıt varsa görevin çalışmadığı anlaşılır, ama
  kuyruk boşken "çalışıyor mu" sorusunun cevabı yok. Son çalışma kaydı iki durumu da gösterir.
- **Hatayla biten çalışma yazılmaz.** Cron kurulu olup işin her seferinde düşmesi de eksik sayılmalı.
- **Aralık, önerilen sıklığın birkaç katı.** Örnek: webhook gönderimi dakikada bir önerilir, sınır 10 dakika. Tek
  kaçan çalışma adımı kırmızıya çevirmesin.
- **Bildirim adımı için tek koşul yetmedi.** Yalnız gizliye bakılsaydı Stripe'a adres girilmemiş olabilirdi. Yalnız
  olaya bakılsaydı, gizli silindiğinde imzalar doğrulanamazken adım yine yeşil görünürdü.
- **Anahtarlar yanıta girmez.** Yanıtta yalnız dolu mu bilgisi ve kip var. Test, üç gizli değerin gövdede olmadığını
  ölçüyor.
- **Taslaktan sapmalar:**
  - "Nasıl yapılır" bir bağlantı değil, satır içinde açılan açıklama. Panelde yardım sayfası yok, rehber de sunulmuyor.
  - Yasal adım İngilizce yayını saymıyor, çünkü paywall Türkçe.

## Kırma denemeleri — 14/14 kırmızı

| Deneme | Sonuç |
|---|---|
| K1 `rk_live_` canlı sayılmaz | 1 test düştü |
| K2 bildirim gizliye bakmaz | 1 |
| K3 e-posta her DSN'de tamam | 2 |
| K4 yasal İngilizceye bakar | 1 |
| K5 Meta yalnız hesaba bakar | 1 |
| K6 sınır anı eksik sayılır (`>=`) | 1 |
| K7 hatalı çıkış da yazılır | 1 |
| K8 listede olmayan komut da yazılır | 1 |
| K9 bilinmeyen uygulamada 404 yok | 1 |
| K10 "6'sı" yerine "6'si" | 1 (panel) |
| K11 tamam adımda da bağlantı | 1 (panel) |
| K12 sayaç hepsini tamam sayar | 2 (panel) |
| K13 "Nasıl yapılır" hep açık | 1 (panel) |
| K14 `aria-expanded` yok | 1 (panel) |

Betik: `$TMPDIR/…/scratchpad/kir-p7c.py`; her denemede dosya yedeklendi ve geri yüklendi.

## Ölçüm

- **Testler:** 4 yeni çekirdek testi (44 doğrulama). Bunlardan biri gerçek konsol uygulamasıyla
  (`ApplicationTester`) `app:webhook:gonder`'i koşturup kaydın düştüğünü ölçüyor. Panel testi 6 (2'si yeni).
- **Tam kontrol:** `bin/kontrol` YEŞİL, 745 çekirdek testi.
- **Geliştirme ortamında:**
  - Göç uygulandı.
  - `bin/console app:webhook:gonder` 0 koduyla bitti ve `zamanli_gorevler` tablosuna `2026-11-11 07:50:03+00`
    satırı düştü.
  - Girişsiz `curl` isteği `302 → /panel/giris` döndü.
- **Tarayıcıda: KOŞULMADI.** Chrome eklentisi bağlı değildi. Görsel kontrol P7b ile birlikte bekliyor.
