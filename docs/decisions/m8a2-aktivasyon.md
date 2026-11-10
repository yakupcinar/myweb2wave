# M8a2 — aktivasyon dizisi: ödeyip bağlanmayana hatırlatma (2026-11-10)

Karar `pre-setup.md` A22 · önceki `m8a1-postmark.md`.

> **Sonra değişti (2026-11-10, M9a):** mağaza adresi artık `.env` / `uygulama_magaza_adresleri` değil, panel → Ayarlar (`uygulama_ayarlari.magaza_adresi`); `posta_dizisi.stripe_subscription_id` sütunu M8b'de `kaynak` oldu. Bkz. `m9a-uygulama-kimligi.md`, `m8b-kurtarma.md`.

## Ne yapıldı

- `App\Posta\AktivasyonDizisi` + `app:posta:dizi` (15 dk cron). İlk adım, var olan ödeme e-postasıdır.
- Ödeme e-postasından sonra **24. ve 72. saatte**, uygulamaya bağlanmamış kişiye **yeni** giriş bağlantısı gider
  (eskisinin 24 saatlik süresi dolmuştur). `MAGAZA_ADRESI_KEEPCHAT` doluysa App Store adresi de eklenir.
- Gönderim kaydı `posta_dizisi`'nde (abonelik × dizi × adım; göç `Version20261110150000`).
- `GirisBaglantisi::uret` herkese açıldı.

## Ölçüm (önce)

- Bağlanma ölçütü: `baglantilar` → `kisiler.email`. `kisiler`'de `email = lower(email)` kısıtı var, bu yüzden aboneliğin
  e-postası `lower()` ile eşleştirilir.
- Dev'de 3 ödemeli abonelikten 2'si bağlanmamış (test verisi).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | İşlem e-postası, izin yok | pazarlama akışı | Aboneliğin kendisini kullanmak için. Karma izin kuralı (A22) M8b'de |
| 2 | 24 / 72 saat, iki adım | tek hatırlatma / sık seri | Bağlantı 24 saatte dolar. İki deneme yeterli, fazlası şikâyet riski |
| 3 | Gecikmiş adımlardan yalnız sonuncusu | hepsini sırayla | Cron günlerce durursa kişi aynı anda iki e-posta almasın |
| 4 | 7 günden eski ödemeye başlamaz | sınırsız | Özellik açılınca birikmiş listeye toplu gönderim olmasın |
| 5 | Durma: bağlandı / erişim yok / bastırılmış | yalnız bağlandı | İptal etmiş kişiye "kullan" denmez; bastırılmışa gitmez (§57) |
| 6 | Kayıt önce, aynı işlemde gönderim | önce gönder | İkinci cron aynı adımı göndermesin. Gönderim düşerse kayıt ve bağlantı geri alınır, sonra yeniden denenir |
| 7 | Kayıtta yabancı anahtar yok | FK subscriptions | Kayıt yalnız "gitti mi" bilgisi; abonelik tablosunu kesen işlemlere bağlanmasın |

## Test

`AktivasyonDizisiTest` (9):
- ilk adım bir kez, yeni bağlantıyla; 23. saatte gitmiyor;
- gecikmişse yalnız 2. adım, atlanan 1. adım sonra da gitmiyor;
- 1. adımdan sonra 2. adım;
- bağlanana gitmiyor (büyük harfli e-posta da), başka uygulamadaki bağ sayılmıyor;
- `canceled`'a gitmiyor, `trialing` ve `past_due`'ya gidiyor;
- bastırılmış adrese, 8 günlük ödemeye ve ilk e-postası gitmemiş aboneliğe gitmiyor; 167. saatteki gidiyor;
- mağaza adresi varsa yazılıyor;
- gönderim düşünce kayıt ve jeton geri alınıyor, sonra gidiyor;
- komut.

## Kırma (12/12 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| A1 | Adımlar artan sırada işlenir | 🔴 2 |
| A2 | `adim >=` yerine `=` | 🔴 2 |
| A3 | Bağlanma denetimi yok | İlk yama SQL'i bozdu, 9 hata, sahte. Düzeltilmiş yamayla 🔴 1 |
| A4 | Uygulama eşleşmesi yok | 🔴 |
| A5 | Erişim süzgeci yok | 🔴 |
| A6 | Bastırma bakılmaz | 🔴 |
| A7 | 7 gün sınırı yok | 🔴 |
| A8 | İlk e-posta şartı yok | 🔴 |
| A9 | Saat şartı yok | 🔴 3 |
| A11 | İşlem yok (kayıt kalır) | 🔴 |
| A12 | Mağaza adresi yazılmaz | 🔴 |
| A13 | E-posta küçük harfe çevrilmez | 🔴 |

## Gerçek sistem (2026-11-10, dev)

- `app:posta:dizi` çalıştırıldı. İlk koşu 0 e-posta: dev'deki eski ödemeler 7 günden eski.
- 25 saat önce ödemiş geçici bir ölçüm aboneliği eklendi:
  - koşu **1 e-posta**, Mailpit'te 1 ileti ("Aboneliğiniz hazır — uygulamaya bağlanın"), kayıt adım 1;
  - ikinci koşu 0;
  - ölçüm satırları ve Mailpit iletisi silindi.
- Cron tanımlı değil, mağaza adresi boş (Ç124).
- `bin/kontrol` YEŞİL, core 680 test.
