# Yöntem el kitabı — MyWeb2Wave nasıl geliştiriliyor

> **Kimin için:** projeyi devralacak geliştirici, denetçi ya da ürünün
> ödeme sistemine güvenmesi gereken biri. **Ne anlatır:** *neyi* yaptığımızı
> değil, *nasıl* ve *neden öyle* yaptığımızı. Ayrıntılı ölçümler karar
> kayıtlarında (`docs/decisions/`); bu kitap onlara bağlantı verir,
> kopyalamaz — bir olgu tek yerde yaşar (CLAUDE.md kural 7).

## Bölümler

| # | Bölüm | Ne zaman okunur |
|---|---|---|
| 1 | [Çalışma ortamı](01-calisma-ortami.md) — yapay zekâ ile geliştirme düzeni, iki terminal, kurallar | projeye ilk gün |
| 2 | [Ölçerek geliştirme](02-olcerek-gelistirme.md) — blok ritüeli, kırma denemesi, tuzaklar | ilk kod değişikliğinden önce |
| 3 | [Araştırma ve karar](03-arastirma-ve-karar.md) — karar kaydı, açık uçlar, rakip/kaynak araştırması, lisans | bir şey seçmeden önce |
| 4 | [Mimari](04-mimari.md) — statik huni + çekirdek API, sözleşme, uygulama boyutu | sistemi anlamak için |
| 5 | [Ödeme sistemi](05-odeme-sistemi.md) — akış, Stripe, webhook güvenliği, erişim kuralları | para akışına dokunmadan önce |
| 6 | [Doğrulama ve CI](06-dogrulama.md) — `bin/kontrol`, CI, gerçek istek, tarayıcıda test kartları, sırlar | commit'ten önce |
| 7 | [Yolculuk](07-yolculuk.md) — başından bugüne blok blok ne yapıldı, ne öğrenildi | bağlam kurmak için |

## Bu kitap nasıl güncellenir

- Her blok kapanırken (`/belge`) **ilgili bölüme** yeni yöntem ya da ders
  1–5 satır olarak eklenir; `07-yolculuk.md`'ye bloğun tek satırı.
- Bölüm ~150 satırı geçerse alt bölüme bölünür (ör. `05a-…`, `05b-…`);
  dizin tablosu güncellenir. Amaç: okuyucu (insan ya da yapay zekâ) yalnız
  ihtiyacı olan bölümü açsın.
- Karar ya da ölçüm **burada değil**, `docs/decisions/` ve
  `docs/pre-setup.md`'de yazılır; burada yalnız bağlantı.
