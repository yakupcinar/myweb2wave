# L13 — Panel oturumu: 8 saat, veritabanında, düşünce açık uyarı (Ç41, Ç35)

2026-10-26 · karar `pre-setup.md` A13 (2FA şimdilik hayır).

## Ölçüm (önce)
- Oturum ömrü örtük: PHP `session.gc_maxlifetime = 1440` sn (Ç41) — 24 dk
  boşta kalan düzenleyici oturumu rastgele düşer.
- Oturumlar kabın dosya sisteminde (`var/sessions`) — her dağıtımda
  yönetici çıkış yapmış olur (Ç35).
- Oturum düşünce Puck kaydı `fetch` ile giriş sayfasına yönleniyor, HTML
  dönüyor, `json()` patlıyor → ekranda "Bağlantı hatası — kaydedilmedi"
  (yanıltıcı).

## Karar
- `framework.session.handler_id` = `PdoSessionHandler(DATABASE_URL)`;
  `sessions` tablosu göçle (`Version20261026120000`, Symfony'nin pgsql
  şeması). `gc_maxlifetime: 28800` (8 sa boşta). Test ortamı mock depo
  (değişmedi).
- Düzenleyici: `oturum.ts` `oturumDustu(y)` = yanıt yönlendirilmiş mi
  (panelin JSON uçları başka yere yönlendirmez — ölçüldü); öyleyse
  "Oturumun süresi doldu — değişikliklerin bu sekmede duruyor. Yeni sekmede
  panele giriş yap, sonra burada yeniden Kaydet'e bas." Puck verisi
  sekmede kalır (sayfa yenilenmez).
- **Reddedilen:** Redis (yeni servis; Postgres zaten var) · kalıcı "beni
  hatırla" çerezi (tek yönetici, istenmedi) · JSON uçlarında 401 giriş
  noktası (güvenlik yapılandırması değişir; istemci tarafı ayrım yetti) ·
  `pathname` denetimi (tuzak §10: ölçülmüyordu, kaldırıldı).

## Ölçüm (sonra) — gerçek istek, yerel çekirdek
Yoklama: `sessions` boşalt → `cache:clear` → `GET /panel/giris` (CSRF jetonu
oturum açar) → satır sayısı + `sess_lifetime − now`:

| Durum | Sonuç |
|---|---|
| karar | `1 | 28799` — DB'de, 8 sa; `var/sessions` boş |
| K1 `handler_id` yok | `0 | 0` — dosyaya gitti |
| K2 `gc_maxlifetime` yok | `1 | 1439` — 24 dk |
| geri (ilk) | `1 | 1440` — **sahte**: yapılandırma geri konduktan hemen sonraki ilk istek eski kapla koştu; iki tekrar `28799` |

Girişsiz Puck kaydı (tarayıcı gibi, 302'den sonra GET): 1 yönlendirme →
`/panel/giris` 200 `text/html`.

## Test
`oturum.test.ts` 2 (yönlenen = düştü; JSON = düşmedi); editör 46/46.
K3 (`oturumDustu` hep `false`) 🔴. İlk K3 denemesi **koşulmadı** (prettier
satırı birleştirmişti, yama tutmadı, EXIT=0 → yeşil değil, tuzak §8);
yeniden uygulanınca kırmızı.

## Ölçülmeyen
Düzenleyicide mesajın görünmesi (DOM testi yok, panel şifresi kullanıcıda).
Kullanıcı bakacak: panelde giriş yap, başka sekmede çıkış yap, Puck'ta
Kaydet → oturum mesajı, değişiklikler yerinde.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 222 çekirdek testi (göç test DB'sine de).
