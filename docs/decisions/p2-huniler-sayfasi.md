# P2 — Huniler sayfası panel uygulamasında (React, 2026-11-03)

Kullanıcı 2026-11-03: döngü, sıradaki görev. Plan (B5, `panel-mimari-arastirmasi.md`): Huniler — liste, yeni, varsayılan, sil/arşiv, kurallar; JSON uçlar + sayfa; Twig silinir.

## Ölçüm (başlangıç)

Twig `panel/huniler.html.twig` + `PanelHuniController`'da 7 form ucu (liste, yeni, kural ekle/sil/taşı, kaldır, varsayılan), flaş iletili yönlendirmeler; jeton `huni-<app>`. React kabuğu P1'den beri `/panel`, `/panel/baglam`, `/panel/anahtarlar`; nav'da Huniler düz bağlantıydı.

## Karar

- **Sözleşme önce:** `/panel/api/uygulamalar/{app}/huniler` (GET liste · POST yeni → 201 {ad, duzenle}), `…/huniler/{ad}` (DELETE → {sonuc: silindi|arsivlendi}), `…/huniler/{ad}/varsayilan` (POST 204), `…/kurallar` (POST 204), `…/kurallar/{id}` (DELETE 204), `…/kurallar/{id}/tasi` (POST {yon} 204); şema `PanelHunileri`. Hatalar `PanelHatalari` (403 jeton, 404 uygulama/kayıt, 422 doğrulama).
- **Jeton kabuğunki** (`panel-api`); düzenleyicinin `huni-<app>` jetonu bu uçlarda GEÇMEZ (ölçüldü).
- Doğrulama yine çekirdekte (`Huniler`), uç yalnız çevirir — iş kuralı değişmedi (Ç41 arşiv, Ç70 süreli "Aç").
- Sayfa (`sayfalar/Huniler.tsx`): uygulama başına huni tablosu + yeni huni formu + kural tablosu/formu. Kaldırma iki adımlı (tarayıcı onay penceresi değil). "Düzenle" ve yeni huniden sonra düzenleyiciye **tam sayfa** geçiş — düzenleyici P3'e kadar ayrı (Twig + Puck) sayfa.
- Twig liste, form uçları, `huniler.html.twig` ve yalnız onun kullandığı `duzen.html.twig` silindi; `panel_huniler` rota adı kabuğa geçti (düzenleyicinin "← Huniler" bağlantısı aynı).
- **"AI ile yeni huni" yapılmadı:** huniye bağlı olmayan sohbet ucu gerekir ve gerçek Claude anahtarı yok (A2: AI kapalı) — Ç93.

## Ölçüm (gerçek sistem)

Panel üretim derlemesi geçti. Gerçek HTTP (oturumsuz): `/panel/huniler` ve `/panel/api/uygulamalar/keepchat/huniler` → 302 `/panel/giris`; eski form ucu `POST /panel/huniler/keepchat/yeni` → 405. **Tarayıcıda denenmedi:** panel girişi kullanıcının kendi hesabı (Ç93).

## Kırma denemeleri (9/9 kırmızı)

Q1 yazma jetonsuz · Q2 düzenleyici jetonu da geçer · Q3 "Aç" süresiz canlı adres · Q4 kopya kaynağı gitmiyor · Q5 yazmada uygulama denetimi yok · F1 kaldırma tek adım · F2 yeni huniden sonra düzenleyiciye gidilmez · F3 yukarı taşı aşağı gönderir · F4 varsayılanda da kaldır. PHPStan düzeltmesinden sonra Q1–Q5 yeniden koşuldu: yine kırmızı.

## Kontrol

`bin/kontrol` 237 s, EXIT=0, `KONTROL: YEŞİL` — core 413 test / 3.277 doğrulama, panel testleri dahil (önceki koşu PHPStan'da kırmızıydı: test yardımcısı `mixed`).
