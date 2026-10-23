# Dağıtım — test modu (pre-setup C6)

Tek sunucu, Docker: `web` (Caddy, TLS + statik huni) → `core` (Symfony,
iç ağ :8080) → `db` (Postgres, dışarı kapalı). Ölçümler ve kırma
denemeleri: `docs/decisions/dagitim.md`.

## 1 · Bir kez: sunucu (kullanıcı)

1. Ubuntu 24.04, en az 2 GB RAM; kendi **ssh anahtarınla** (şifre değil).
2. DNS: `API_ALANI` ve `HUNI_ALANI` için iki **A kaydı** → sunucu IP'si.
   Caddy sertifikayı ancak kayıt yayıldıktan sonra alabilir.
3. Stripe panelinde (**test modu**) Webhooks → uç nokta ekle:
   `https://<API_ALANI>/webhooks/stripe`, olaylar
   `checkout.session.completed`, `customer.subscription.created`,
   `customer.subscription.updated`, `customer.subscription.deleted`.
   Verilen `whsec_` → `STRIPE_WEBHOOK_SECRET`.
4. E-posta sağlayıcısı (Ç30): SMTP bilgisi → `MAILER_DSN`. Gönderen alan
   adında SPF/DKIM yoksa kodlar spam'e düşer.

## 2 · Bir kez: hazırlık (root)

```
scp deploy/sunucu-hazirla.sh root@<ip>:
ssh root@<ip> 'bash sunucu-hazirla.sh > /root/hazirla.log 2>&1; echo EXIT=$?'
```

Docker, güvenlik duvarı (22/80/443), `dagitim` kullanıcısı, şifreli ssh ve
root girişi kapalı, depo `/opt/myweb2wave`, günlük yedek (03:15) ve günlük
serbest yanıt temizliği (03:30; 90 günden eski ziyaretler). Bundan
sonra giriş: `ssh dagitim@<ip>`.

## 3 · Değerler (dagitim kullanıcısı)

```
cd /opt/myweb2wave
cp deploy/env.uretim.ornek deploy/.env.uretim && chmod 600 deploy/.env.uretim
```

Doldur; rastgele değerler `openssl rand -hex 32`. Panel şifresinin
**özeti**: `docker compose -f deploy/compose.yaml run --rm core php bin/console security:hash-password`
→ `PANEL_SIFRE_OZETI='…'` (**tek tırnak**, tuzak §26). Panel:
`https://<API_ALANI>/panel`. `UYGULAMA_ANAHTARI_KEEPCHAT`
KeepChat sunucusundakiyle **aynı**. Sırlar sohbete, depoya yazılmaz.

## 4 · Her dağıtım

```
cd /opt/myweb2wave && git pull --ff-only
deploy/dagit.sh > /tmp/d.log 2>&1; echo EXIT=$?; tail -5 /tmp/d.log
```

Sıra: ön kontrol (sk_test_, whsec_, sır uzunlukları, null mailer) →
derleme → yedek → göç → servisler → **ölçüm**: `https://<API_ALANI>/health`
sürümü == commit, huni 200. Son satır `DAGITIM TAMAM sürüm=<sha>` değilse
dağıtım olmamıştır.

## Yedek

`/var/backups/myweb2wave/app-*.dump`, 14 gün. Geri yükleme:
`docker exec -i myweb2wave-uretim-db-1 pg_restore -U app -d <boş_db> < dosya`.
⚠️ Yedek aynı diskte (Ç33).

Anket serbest yanıtlarının metni ziyaret tarihinden 90 gün sonra ana DB'den
silinir (`app:huni-metin-cevaplari-sil`). Günlük yedek temizliği nedeniyle
silinen metin, yedeklerde en çok yaklaşık 105 güne kadar kalabilir.
