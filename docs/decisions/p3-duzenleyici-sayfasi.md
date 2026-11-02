# P3 — Puck düzenleyicisi panel uygulamasının sayfası (2026-11-03)

Kullanıcı 2026-11-03: döngü, sıradaki görev. Plan (B5): düzenleyici panelin sayfası (sayfa yenilemeden); `huni-surukle.html.twig` silinir.

## Ölçüm (başlangıç)

Düzenleyici ayrı giriş (`src/main.tsx`) + Twig sayfası: taslak, imza, uç adresleri ve `huni-<app>` jetonu `#puck-duzenleyici` özniteliklerinde; sayfaya özgü stil Twig'de satır içi. Kaydet/durum/yayınla/geri yükle/medya uçları `huni-<app>` jetonu ister.

## Karar

- Düzenleyici modül oldu (`src/duzenleyici.tsx`, `git mv`; davranış aynı). Twig'deki stil `duzenleyici.css`'e aynen taşındı. Ayrı giriş kalktı — derlemede tek giriş (panel uygulaması).
- Sayfa `sayfalar/HuniDuzenle.tsx`, yol `/panel/huniler/:app/:ad` **sol menüsüz** (Puck tam ekran), **tembel yüklenir** — Puck (≈ 500 KB JS + 107 KB CSS) yalnız düzenleyici sayfasında iner.
- Açılış tek uçtan: `GET /panel/api/uygulamalar/{app}/huniler/{ad}/duzenleyici` (sözleşme) → taslak, imza, planlar, tema, yazı tipleri, uç adresleri ve düzenleyici jetonu (`huni-<app>`). Düzenleyici uçları ve jetonları **değişmedi** (P3'ün kapsamı değil).
- `GET /panel/huniler/{app}/{ad}` artık panel kabuğunu verir (derin bağlantı, yenileme); bilinmeyen huni/uygulama yine 404.
- Huniler sayfasında "Düzenle" ve yeni huni → düzenleyiciye **sayfa yenilemeden** (`Link` / `navigate`).
- **Stil yalıtımı** (`useYalitim`): bkz. ölçüm. Düzenleyici açıkken panel stil düğümü (işaret kuralı `#panel-stil-isareti` ile bulunur) `media="not all"`, Twig sayfasının gövde kuralı eklenir; çıkınca ikisi de geri.

## Ölçüm (tarayıcı, Chrome; geçici düzenek)

Panel girişi kullanıcının hesabı — giriş yapılmadı. Bunun yerine geçici Vite düzeneği (commit edilmedi, ölçümden sonra silindi): gerçek `Duzenleyici` örnek tanımla, dört koşulda; önizleme çerçevesinde 16 öğe + düzenleyici kabuğunda 100 öğe × 19 hesaplanmış özellik karşılaştırıldı.

| Koşul | Önizleme farkı | Kabuk farkı |
|---|---|---|
| A2 eski Twig koşulu (gövde kuralı, panel stili yok) | — (temel) | — |
| B panel stili, yalıtımsız | 39 (genişlik 520 → 438 px, `box-sizing`, koyu zemin, yazı tipi) | 253 |
| C panel stili + yalıtım | **0** | **0** |
| D yalıtım, Puck'ı bekletmeden | 0 | 0 → bekletme gereksiz, kaldırıldı (§10) |

Yeni tuzak §43. Üretim derlemesinde işaret kuralı küçültmeden sağ çıktı (`#panel-stil-isareti{--panel-stil:1}`); ölçüm geliştirme sunucusunda (`<style>`), üretimde `<link>` — kullanıcı tarayıcısında ölçülmedi (Ç94).

## Kırma denemeleri (6/6 kırmızı)

R1 panel stili kapatılmıyor · R2 çıkınca geri açılmıyor · R3 açılışta medya yerine kayıt adresi · R4 açılış jetonu kabuğunki (düzenleyici uçlarında geçmez) · R5 olmayan huniye kabuk (404 yok) · R6 "Düzenle" tam sayfa.

## Kontrol

`bin/kontrol` 204 s, EXIT=0, `KONTROL: YEŞİL` — core 413 test / 3.291 doğrulama, panel 96 test (önceki koşu PHPStan'da kırmızıydı).
