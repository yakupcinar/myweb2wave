# M8a1 — Postmark köprüsü ve bastırma listesi (2026-11-10)

Karar `pre-setup.md` A22 · önceki `m7c2-yazma-araclari.md`.

## Ne yapıldı

- `symfony/postmark-mailer` kuruldu. Üretimde `MAILER_DSN=postmark+api://<anahtar>@default`; dev Mailpit, test `null://null`.
- `posta_bastirma` (göç `Version20261110120000`): akış başına (e-posta, akış) bastırma durumu. Satır silinmez, eski tarihli bildirim yenisini ezmez.
- `POST /webhooks/postmark`: Subscription Change → listeye. HTTP Basic (`postmark:<POSTMARK_WEBHOOK_SIFRE>`), şifre boşsa 404.
- `PostmarkTeslimDinleyicisi`: pasif alıcı (406) olayı → listeye (tuzak §57).
- Ödeme sonrası giriş e-postası kuyruğu işlem akışında bastırılmış adresi atlıyor, yeniden etkinleşince gönderiyor.

## Ölçüm (önce)

Köprü kaynağı: 422 + ErrorCode 406'da dağıtıcı varsa olay yayıp **başarılı döner**. Postmark belgesi: webhook imzası yok (Basic + IP), 4xx yeniden denenmez, 5xx denenir; Subscription Change alanları. Dev'de 3 ödemeli abonelikten 2'si bağlanmamış (test verisi).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Yalnız Subscription Change | Bounce + SpamComplaint ayrı | Bastırmanın kendisini (ve geri alınmasını) tek tür söylüyor |
| 2 | Satır kalır, `bastirildi` bayrağı | silmek | Sırası karışmış yeniden deneme yeni durumu ezmesin |
| 3 | Biçim dışı gövde 400, ilgisiz tür 200 | hepsine 200 | 400 Postmark'ta tekrar edilmez (düzelmez), ilgisiz tür kuyruğu tıkamaz |
| 4 | Adres log'a yazılmaz | log'da adres | Kişisel veri; kayıt tabloda |
| 5 | Kuyruk bastırılmışı atlar, işaretlemez | "gitti" yazıp geçmek | Yeniden etkinleşince gitsin; 20'lik kuyruk kalıcı başarısızla tıkanmasın |

## Test

`PostmarkWebhookTest` (7): Basic yok / yanlış 401, şifre boş 404, bastırma ve geri alma, eski tarihli bildirim, akış başına satır, ilgisiz tür 200 / biçim dışı 400, 406'da `send()` atmaz ve listeye yazılır (akış başlığından). `GirisBaglantisiTest` +1: bastırılmış adrese gitmez, broadcast bastırması sayılmaz, etkinleşince gider.

## Kırma (11/11 🔴)

K1 dinleyici kayıtsız · K2 kuyruk listeye bakmaz (ilk yama SQL söz dizimini bozuyordu, 10 hata — sahte; yeniden koşuldu, 1 düşüş) · K3 akış süzgeci yok · K4 `bastirildi` bakılmaz · K5 tarih karşılaştırması yok · K6 Basic denetimi yok · K7 boş şifre açık · K8 kayıt türü süzülmez · K9 akış başlığı yoksa yanlış varsayılan · K10 akış başlığı okunmaz · K11 adres denetimi yok — hepsi 🔴.

## Gerçek sistem (2026-11-10)

- Dev'de `POST /webhooks/postmark` şifre boş → 404.
- `mailer:test` gerçek Postmark API'sine: `POSTMARK_API_TEST` anahtarı EXIT 0, kontrol grubu yanlış anahtar "valid Server token (code 10)" EXIT 1 — yanıt Postmark'tan.
- Gerçek hesap, gönderen imzası, DNS ve webhook ulaşımı ölçülmedi (Ç123). Ortam: disk %98 dolu (5,4 GB boş).
- `bin/kontrol` YEŞİL, core 671 test.
