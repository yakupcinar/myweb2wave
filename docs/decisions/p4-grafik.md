# P4 — Rapor grafikleri: günlük ziyaret ve ödeme (2026-11-03)

Kullanıcı 2026-11-03: döngü, sıradaki görev. Plan (B5): günlük ziyaret / ödeme serisi (Recharts).

## Ölçüm (başlangıç)

Rapor yalnız sayı ve listeydi (`/panel/api/rapor`, aralık 7/30/90 gün, kayan pencere). Recharts kurulu değildi. Kaynak: T4 olay deposu (`huni.ziyaret`, `odeme.baslatildi`, `odeme.tamamlandi`).

## Karar

- Uç: `PanelRaporu.gunluk` — aralığın her **UTC takvim günü** (en eski önce), boş gün 0 (`generate_series` + olay deposu). Sayımlar kayan pencere (son N×24 sa), seri takvim günü: aynı aralıkta bugünün yarısı ile başlangıç gününün parçası farklı sayılabilir — kartlarda "N gün (UTC)" yazar.
- Biçim (dataviz yöntemi): ölçekler farklı → **iki ayrı grafik, çift eksen yok**. Günlük sayım → çubuk (ince, üstü 4 px yuvarlak, 2 px aralık). Tek seride gösterge yok (başlık adlandırır), iki seride gösterge; ipucu (gün + değerler, metin renginde); her grafikte "Tablo olarak göster"; kayıtsız aralıkta grafik yerine boş durum.
- Renk: kategorik sıra 1–2 (mavi, turuncu), panelin yüzeylerine karşı doğrulandı (`validate_palette.js`: açık `#ffffff`, koyu `#141821` — tüm denetimler geçti); `--seri-1/2` belirteçleri, koyu adımlar ayrı.
- Recharts 3.10.1 (tam sürüm, 14 günden eski). Grafik bileşeni **tembel parça**: ana paket 417,8 KB (129 KB gzip) kaldı, grafik parçası 357,7 KB (102 KB gzip) yalnız Rapor'da iner.

## Ölçüm (tarayıcı, Chrome; geçici düzenek, gerçek veri)

Panel girişi kullanıcının hesabı — giriş yapılmadı. Geçici Vite düzeneği (commit edilmedi, silindi) yerel veritabanının son 30 gününü (104 ziyaret, 13 ödemeye geçti, 1 ödedi) gerçek bileşenle çizdi: koyu tema (sistem ayarı), etiket çakışması yok, eksen/ızgara geri planda, ipucu "20 Ekim Sal · Ziyaret: 48". Açık tema tarayıcıda görülmedi (renkleri doğrulayıcı geçti).

## Kırma denemeleri (5/5 kırmızı)

G1 boş gün yok (iç birleşim) · G2 aralık hep 30 gün · G3 "ödedi" yerine "ödemeye geçti" sayılır · F1 tek seride de gösterge · F2 boş durum yok. Ölçülmeyen: tablo gününün UTC biçimi (kap saat dilimi UTC — yerel saatle bozma burada kırmızı olmaz).

## Kontrol

`bin/kontrol` 209 s, EXIT=0, `KONTROL: YEŞİL` — core 414 test / 3.304 doğrulama, panel 98 test.
