# L4 — Puck: yalnız üç hazır soru türü + eklemede benzersiz kimlik (Ç61 (1)(2))

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
- Ekleme menüsünde dört soru girişi: genel "Soru" + "Tek seçim / Çoklu
  seçim / Serbest yanıt sorusu" (`15e7585`). Kayıttan dönen her soru
  `tanimdanPuck`'ta "Soru" türüyle açılıyordu.
- Hazır türlerin varsayılan kimliği sabit (`tek-secim-sorusu` …): aynı
  türden iki blok → kayıt `Tanim.php` "kimlik '…' tekrar ediyor" ile red.
  Tanıtım (`tanitim`) ve Yorum da aynı.
- Puck 0.23 kaynağı: `resolveData` ekleme sonrası `trigger: "insert"` ile
  çağrılıyor; parametrelerde öteki blokların içeriği YOK (yalnız
  `root`, `parent`). "Çoğalt" (`duplicateAction`) `resolveData` çağırmıyor.

## Karar
- `Bilesenler`'den "Soru" kalktı; `tanimdanPuck` yanıt biçimine göre
  `TekSecim` / `CokluSecim` / `SerbestYanit` açar (`SORU_TURU`). Kayıt şeması
  (`tur: "soru"`) değişmedi.
- Eklemede kimliğe Puck blok kimliğinin son 4 karakteri eklenir
  (`tek-secim-sorusu-9f3a`); yalnız `insert` — yüklenen/yayındaki kimlik
  değişmez (eski ziyaret verisiyle eşleşme). Tanıtım, üç soru türü, Yorum.
  Paywall tek olduğundan eklenmedi.
- **Reddedilen:** `soru-2, soru-3` sıralı ek (resolveData öteki blokları
  görmüyor; sayaç için modül düzeyi durum gerekirdi) · `onChange`'te
  çakışanı düzeltmek (kullanıcının yazdığı kimliği sessizce değiştirirdi).
- **Kalan (Ç65):** "Çoğalt" ile kopyalanan blok aynı kimliği taşır; kayıt
  açık hata ile reddeder.

Tuzak §10: `benzersizKimlik`'teki karakter ayıklama + boş ek koruması
ölçülmüyordu (Puck kimliği `Tür-uuid`, son 4 karakter hep küçük harf
onaltılık) → kaldırıldı.

## Test
Editör 34/34 (+6): menüde "Soru" yok ve tam 3 soru türü · eklemede iki
farklı blok farklı kimlik, çekirdeğin kuralına (elle yazılmış
`^[a-z0-9_-]{1,64}$`) uyar, `load`'da değişmez · her eklenebilir tür için
ek gelir (`it.each`) · tanım → Puck türleri + gidiş-dönüş kayıpsız.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `tanimdanPuck` hep `TekSecim` | 🔴 gidiş-dönüş testi |
| K2 | "Soru" menüye geri | 🔴 menü testi |
| K3 | ek yüklemede de uygulanır | 🔴 benzersiz/`load` testi |
| K4 | ek yok | 🔴 benzersiz/`load` testi |
| K5 | Tanıtım'ın `resolveData`'sı silindi | 🟢 **TUTMADI** — yama girinti farkıyla soru yerine Tanıtım'a düştü ve hiçbir test görmedi → test eksik; tür başına `it.each` eklendi |
| K5a/b/c | Tanıtım / soru / Yorum `resolveData` silindi | 🔴 1 / 4 / 1 test |

## Tarayıcı
**Koşulmadı.** Yerel panel şifresi kullanıcının `core/.env.local`'ında
(düz metin değil); kullanıcının şifresiyle giriş yapılmadı. Kullanıcı
bakacak: `/panel/huniler/keepchat` → Puck → ekleme menüsünde 3 soru türü,
aynı türden iki blok ekle → kaydet başarılı.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0.
