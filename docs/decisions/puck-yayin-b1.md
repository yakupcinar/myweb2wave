# Blok: b1 — yayınla, önizleme, sürümler Puck düzenleyicisinde (Ç45) — 2026-10-21

## Ne yapıldı
Sürükle-bırak düzenleyicinin üst çubuğu artık: **durum notu · Kaydet ·
Önizle ↗ · Yayınla · Sürümler**. Puck'ın kendi "Publish" düğmesi kaldırıldı
(bizde kaydetmek demekti, yanıltıcıydı — Ç45). Sürüm menüsü: sürüm, yayında
işareti, zaman, ziyaret sayısı, "Taslağa geri yükle". Klasik düzenleyici
dokunulmadan duruyor (bağlantı "yedek" diye etiketlendi) — b2'de silinir.

Çekirdeğe üç JSON ucu (`PanelHuniController`): `GET …/durum` (sürümler,
yayından farklı mı, taze önizleme bağlantısı, `no-store`), `POST
…/durum/yayinla` (200 `{surum}` / 422 `{hatalar}`), `POST
…/durum/surumler/{n}/taslaga` (200 `{tanim}` / 404). CSRF `X-CSRF-Token`
başlığından (taslak ucuyla aynı). Klasik sayfanın form uçları aynen kaldı.

## Neden böyle

| Karar | Elenen | Neden |
|---|---|---|
| Üçü düzenleyicinin içinde | ayrı yayın sayfası (bugünkü klasik) | Rakip ölçümü: FunnelFox, Superwall, RevenueCat, web2wave hepsi düzenleyicide (`huni-editoru-arastirmasi.md` § Ek 2026-10-21) |
| JSON uçları (ayrı yollar) | form uçlarına `Accept` ile JSON dalı; form + sayfa yenileme | Puck tek sayfa: yenileme kaydedilmemiş işi siler. Ayrı yollar klasik sayfanın davranışına dokunmuyor (b2'ye kadar ikisi yan yana) |
| **Kaydedilmemiş değişiklik varken Önizle / Yayınla / Geri yükle kapalı** | açık bırakıp uyarmak | Önizleme ve yayın KAYDEDİLMİŞ taslağı kullanır — açık olsalar kullanıcı gördüğünden başkasını yayınlar; geri yükleme kaydedilmemiş işi siler. Tarayıcı diyaloğu (confirm) kullanılmadı |
| "Kaydedilmemiş" = `puckTanim(güncel)` metni ≠ son kaydedilenin metni | `onChange` sayacı | Puck açılışta da `onChange` çağırabilir; içerik karşılaştırması yalancı uyarı vermez. İkisi aynı çeviriden geçtiği için JSONB anahtar sırası sorun değil (tuzak §28) |
| Geri yüklemede Puck `key` ile yeniden açılır | `dispatch` ile veriyi değiştirmek | `data` yalnız ilk değer; yeniden açmak geçmişi (geri al) de temizler — eski sürümün üstüne geri almak anlamsız |
| Önizle bir bağlantı (`<a target=_blank>`), bağlantı her durum okumasında taze | tıklayınca istek atıp pencere açmak | `await` sonrası `window.open` açılır pencere engelleyicisine takılır; anahtar 1 saat geçerli, her kaydet/yayınla sonrası yenileniyor |

## Testler
- `core/tests/Controller/PanelHuniTest.php` +4: durum (girişsiz → giriş
  sayfası, sürümler, fark, geçerli önizleme anahtarı, `no-store`); JSON
  yayınla (v1, ikinci kez 422 "yayınlanacak değişiklik yok", tek sürüm);
  JSON geri yükle (tanım döner, taslak değişir, **yayın değişmez**, olmayan
  sürüm 404); iki uçta CSRF (boş/yanlış → 403, hiçbir şey değişmez).
- `core/editor/src/ustCubuk.test.ts` (4): düğme kuralları.
- Üst çubuğun React kısmı (imza karşılaştırması, yeniden açma) testsiz —
  tarayıcıda ölçüldü (aşağıda); Ç53.

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| S1 | kaydedilmemişken Yayınla açık | 🔴 1 test |
| S2 | kaydedilmemişken Önizle açık | 🔴 1 test |
| S3 | kaydedilmemişken Geri yükle açık | 🔴 1 test |
| S4 | yayındakiyle aynıyken Yayınla açık | 🔴 1 test |
| S5 | JSON yayınla CSRF denetimi yok | 🔴 PHPUnit 1 |
| S6 | JSON geri yükle CSRF denetimi yok | 🔴 PHPUnit 1 |
| S7 | durum yanıtı `no-store` değil | 🔴 PHPUnit 1 |
| S8 | yayın hatası 200 döner | 🔴 PHPUnit 1 |
| S9 | durum route'una `IsGranted('PUBLIC_ACCESS')` | ⚪ **tutmadı** — koruma route'ta değil `security.yaml` `access_control`'da (`^/panel` → `ROLE_YONETICI`) |
| S9b | `access_control`'a `…/durum` için `PUBLIC_ACCESS` | 🔴 PHPUnit 1 |

## Ölçüm
- `bin/kontrol` **EXIT=0, 153 sn**; çekirdek **173** (+4), düzenleyici 14
  (+4), huni 24, bileşen 18.
- Tarayıcı (Chrome, panel oturumu, yerel; deneme hunisi `b1deneme`,
  `fitness`'tan kopya — panelden "Huni aç"):
  1. açılış: not "Kaydedildi, yayında değil", Puck "Publish" yok
  2. sayfa başlığı değişti → "Kaydedilmemiş değişiklik — önce kaydet",
     Önizle/Yayınla kapalı, Önizle'nin `href`'i yok
  3. Kaydet → bildirim + "Kaydedildi, yayında değil", Önizle/Yayınla açık
  4. Yayınla → "v1 yayında…", not "Yayındakiyle aynı", Yayınla kapalı,
     menüde `v1 · yayında · … · 0 ziyaret`
  5. başlık "B1 v2" → Kaydet → Yayınla (v2); menüden v1 → "Taslağa geri
     yükle" → alan "B1 Deneme", not "Kaydedildi, yayında değil", Yayınla açık
  6. Önizle bağlantısı → huni `document.title` = "B1 Deneme" + önizleme şeridi
- `b1deneme` yerel veritabanında kaldı (huni silme yok, Ç41).

## 2026-10-23 — Dar ekran düzenlemesi

Puck üst çubuğunun yaklaşık 720 px altında satır kırmasına izin verildi; durum
mesajı ayrı satıra alınır, düğmelerin dokunma alanı en az 40 px olur ve sürüm
menüsü dar pencerede taşmadan kaydırılır. 420 px altında sürüm menüsü tam satıra
geçer. Safari'de (yerel panel, dar pencere ve Puck'ın küçük ekran önizlemesi)
sürüm menüsünün pencerenin solundan kesildiği görüldü. Dar ekranda menü artık
iki yandan sabit boşlukla ekrana sığar. Safari yenilemesinden sonra v1–v5 ve
"Taslağa geri yükle" düğmeleri menü içinde görünür kaldı; taslakta değişiklik
yapılmadı. Ekran görüntüsüyle önce kesik, sonra tam görünen menü doğrulandı.
Twig lint: 1 dosya geçerli. `bin/kontrol`: EXIT=0, KONTROL: YEŞİL; çekirdek
205 test / 1.294 assertion. Aşama günlüklerinin süre toplamı 91 sn; ortamda
disk %99, 3,4 GiB boştu. Kontrolün ürettiği `core/config/reference.php`,
koşum öncesi çalışma alanı yedeğine geri alındı (`cmp` ile eşitlik ölçüldü).

b2'ye geçmeden önce kullanıcının Puck'ı gerçek kullanımda denemesi bekleniyor;
klasik düzenleyici yedek olarak kalıyor.
