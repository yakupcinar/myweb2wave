# L3 — Terk edilen ödemede IP/UA temizliği (Ç59)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
İzinli ziyaret checkout açınca `odeme_oturumlari.ip_adresi/user_agent`
yazılıyor; yalnız `checkout.session.completed` webhook'u ya da izin geri
alma siliyordu. Ödenmeyen oturum için ne olay ne zaman temizliği vardı →
H6b'deki "en geç 7 gün" vaadi bu satırları kapsamıyordu. Gerçek kayıt:
L1'de açılan yerel oturum (`L1-olcum` UA'sı, IP dolu) hiç silinmeyecekti.
İki yeni test düzeltmeden ÖNCE kırmızıydı (IP/UA dolu kaldı).

Ölçüm (tuzak §10): ödenmiş oturumun HER yolu (`kuyrugaAl` içinde
`baglamSil`, kuyruğa aldıktan sonra silme, ödenmemiş completed) IP/UA'yı
zaten siliyor → cron'da "tamamlanmadı" koşulu hiçbir satırı değiştirmez;
kaldırıldı.

## Karar
- `SubscriptionSync`: `checkout.session.expired` → o oturumun IP/UA'sı
  aynı işlemde silinir; `metadata.app` aranmaz (temizlik uygulamadan
  bağımsız). Olay `processed_webhook_events`'e yazılır (tekrar korunur).
- `DonusumAktarimi::gonder` (5 dk cron): 48 saatten eski oturumun IP/UA'sı
  silinir. Stripe oturumu en geç 24 saatte kapanır; 48 sa pay.
- `deploy/README.md`: Stripe uç noktasında `checkout.session.expired` de
  seçilir.

**Reddedilen:** yalnız olay (webhook seçilmez/kaçarsa veri kalır) · yalnız
cron (gereksiz 48 sa bekleme) · ayrı komut + ayrı cron satırı (aynı 5 dk
cron'u zaten 7 gün temizliğini yapıyor).

**Sınır:** ödeme webhook'u 48 saatten fazla gecikirse (Stripe 3 güne kadar
yeniden dener) IP silinmiş olur ve `kuyrugaAl` o satın almayı reklam
ağlarına HİÇ göndermez (IP'siz yük yok). Gizlilik lehine bilinçli kabul.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | expired dalı tetiklenmez | 🔴 `testSuresiDolan…` |
| K2 | expired TÜM oturumları siler | 🔴 `testSuresiDolan…` (başka oturum korunmalı) |
| K3 | cron temizliği yok | 🔴 `testCronKirkSekiz…` |
| K4 | sınır 46 sa (47 sa'lik de silinir) | 🔴 `testCronKirkSekiz…` |
| K5 | expired `metadata.app` ister | 🔴 `testSuresiDolan…` — ilk denemede grep "0" saydı (kaçış hatası), yama Python ile yeniden uygulanıp doğrulandı |
| geri | — | 🟢 17/17 webhook testi |

## Gerçek istek
Yerel çekirdeğe, geliştirme webhook sırrıyla imzalı
`checkout.session.expired` (L1'de açılan gerçek Stripe test oturumu):
**200**; satır önce `L1-olcum` + IP dolu, sonra ikisi de NULL.

## Kontrol
İlk iki koşu `core-kur` kırmızı — kod değil, iCloud çakışma kopyaları (Ç63, tuzak §37). Kopyalar silinince **YEŞİL**: EXIT=0, 207 test / 1.302 doğrulama.
