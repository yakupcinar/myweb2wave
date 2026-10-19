# Blok: CI — 2026-10-19

## Ne yapıldı
GitHub Actions `.github/workflows/kontrol.yml`: `main`'e push ve her PR'da
**`bin/kontrol`'ün kendisi** koşar (ubuntu-24.04, Docker). Eylem commit
SHA'sına sabit (`actions/checkout` v7.0.1 → `3d3c42e`).

## Neden böyle
- **Tek tanım:** CI kendi adımlarını tanımlasaydı yerel kapıyla kayardı;
  aynı betik kaymaz. Reddedilen: workflow'da ayrı ayrı PHPStan/PHPUnit adımları.
- **SHA sabitleme:** etiket taşınabilir, SHA taşınamaz.

## Yolda ölçülenler
1. **İlk koşu KOŞMADI:** 4 sn "failure", adım sayısı 0; ek açıklama
   faturalandırma (özel depo, Actions kotası). Kırmızı kodun değildi.
   Kullanıcı kararı: depo herkese açık → `pre-setup.md` §C5.
2. **İlk gerçek koşu gerçek açık buldu:** çekirdek adımları `EXIT=127`.
   `bin/kontrol` `composer install` yapmıyordu; yerelde `vendor/` vardı.
   Düzeltme: `core-kur` adımı. Doğrulama: yerelde `vendor/` silindi → yeşil.
3. Tuzak §21 ve §16'nın `grep` hâli bu blokta yaşandı (`tuzaklar.md`).

## CI kırma denemesi (PR #1)

| Adım | Commit | Koşu | Sonuç |
|---|---|---|---|
| düzeltme sonrası | `7b067de` | 39234871074 | 🟢 OK (2 tests) |
| DB yokken de 200 (K3) | `60a6615` | 39235245149 | 🔴 `testVeritabaniyokken503Doner`, KONTROL: KIRMIZI |
| `git revert` | `5ff829b` | 39235520029 | 🟢 OK (2 tests) |

İki deneme **uygulanmadan** kaldı (desen bulunamadı / `revert -q` geçersiz);
ikisi de "uygulandı?" satırıyla görüldü ve "koşulmadı" sayıldı.

## Ölçüm
CI süresi ~1,5 dk (core-kur 55 sn, imaj derleme dahil). Ortam: ubuntu-24.04,
Docker 28.0.4, disk 86 GB boş.

## Sonradan — main koşusu kırmızı, sebep görünmedi (2026-10-19, PR #3 sonrası)
- Main koşusu 39250747376: `core-kur` EXIT=100; aynı kod PR'da yeşildi.
  Yeniden koşu (aynı kod) **yeşil** → büyük ihtimalle Composer'ın GitHub'dan
  indirmesinde geçici ağ hatası. **KANITLANMADI**: asıl hata satırı görünmedi (Ç22).
- Görünmeme sebebi `bin/kontrol`: kırmızı adımın günlüğünün **ilk** 40
  satırını basıyordu; CI'da onlar imaj derleme gürültüsü. Düzeltme: `tail -40`.
- Kırma: imaj silinip yeniden derletildi + `composer.json`'a olmayan paket →
  yeni hâlde hata satırı görünüyor (1), eski hâlde (`sed -n 1,40p`) görünmüyor (0).

