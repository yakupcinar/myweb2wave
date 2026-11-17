# L12 — Eşzamanlı taslak kaydı: eski imzayla 409 (Ç39)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
`Huniler::taslakKaydet` koşulsuz `UPDATE` — iki sekme aynı taslağı açarsa
sonra kaydeden öncekini sessizce eziyordu (Ç39, Ç53 (4)). `huniler`
tablosunda sürüm/imza sütunu yok.

## Karar
- İmza = veritabanındaki taslak metninin SHA-256'sı
  (`encode(sha256(convert_to(taslak::text,'UTF8')),'hex')`) — **göç yok**;
  JSONB metni aynı veri için kararlı.
- Puck sayfası açılışta `data-imza` alır; kayıtta `{tanim, imza}` yollar.
  Karşılaştırma **aynı `UPDATE … WHERE`** içinde (oku-sonra-yaz iki kaydın
  arasına üçüncüsünü sokardı). Eşleşmezse 409 + `Huniler::CAKISMA`
  ("başka bir sekmede ya da başka biri tarafından değiştirildi —
  sayfayı yenileyin"); taslak değişmez. İmzasız kayıt da çakışma.
- Başarılı kayıt ve sürüm geri yükleme yeni imzayı döner; düzenleyici onu
  saklar (kendi ardışık kayıtları çakışmaz).
- `taslakKaydet`'in imzasız (null) yolu çekirdek içi çağrılar (yeni huni,
  testler) için kalır; panel ucu hep imza ister.
- **Reddedilen:** `updated_at` karşılaştırması (mikro saniye tarayıcıya
  taşınır, eşitlik kırılgan) · sürüm sütunu + göç (imza yetiyor) · son
  yazan kazanır + uyarı (veri kaybı yine olur).

## Test
`PanelHuniTest` 14: iki sekme aynı imza → birinci kaydeder (200, yeni
imza = sayfanın imzası), ikinci eski imzayla 409, taslak "Birinci" kalır;
imzasız 409; güncel imzayla 200. Geri yükleme yanıtındaki imza sayfanınkiyle
aynı. Önce kırmızıydı.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | imza karşılaştırması yok | 🔴 çakışma testi |
| K2 | boş imza "denetleme" sayılır | 🔴 çakışma testi (imzasız) |
| K3 | çakışma 422 | 🔴 çakışma testi |
| K4 | geri yükleme imza dönmez | 🔴 geri yükleme testi |
| K5 | kayıt yeni imza dönmez | 🔴 çakışma testi |

## Ölçülmeyen
Düzenleyicinin imzayı saklayıp yollaması (`main.tsx`) — DOM testi yok
(Ç53 (2)), panel şifresi kullanıcıda. Kullanıcı bakacak: aynı huniyi iki
sekmede aç, birinde kaydet, ötekinde kaydet → "başka bir sekmede…" hatası.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 222 çekirdek testi / 1.405 doğrulama.
