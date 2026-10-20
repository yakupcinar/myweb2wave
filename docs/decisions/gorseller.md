# Blok: E2.3 — huni görselleri (A8) — 2026-10-21

## Ne yapıldı
Panelden görsel yükleme (`POST /panel/medya/{app}`, giriş + CSRF) ve
listeleme; huni tanımında tanıtım adımına **kapak görseli**, soru
seçeneklerine **küçük görsel** (`gorsel` = kimlik). Yüklenen dosyaya
güvenilmez: tür içerikten (`getimagesize`), yalnız PNG/JPEG/WebP; boyut ≤ 5 MB;
çözmeden önce kenar ≤ 8000 ve ≤ 16 megapiksel; sonra GD ile yeniden çizilip
en uzun kenar 1600 px'e indirilir ve WebP'ye (kalite 82) kodlanır. Kimlik =
WebP'nin SHA-256'sı (aynı görsel tek kopya); dosya `var/medya/<id>.webp`
(üretimde kalıcı birim + yedek), `GET /medya/{id}.webp` ile `immutable`,
1 yıl önbellek, `nosniff`. Tanımdaki görsel bu uygulamaya yüklenmiş değilse
yayınlanmaz. Huni sayfası görseli `textContent` gibi güvenli kurar (kimlik
64 hex, adres dışarıdan kurulamaz). Panel düzenleyicisinde seçici + yükleme.

## Neden böyle
- **Yeniden kodlama, olduğu gibi saklamak değil:** EXIF/konum, gömülü betik,
  poliglot (JPEG sonuna eklenmiş HTML) yeniden çizimde kalmaz — testle
  ölçüldü. **SVG yok:** içinde betik taşır.
- **İçerik özetli ad:** dosya hiç değişmez → sonsuz önbellek, CDN'e hazır;
  aynı görsel iki kez yüklenince tek kopya.
- **`var/` altında + uç, `public/` değil:** FrankenPHP `public/`teki dosyayı
  PHP'ye uğramadan verir → üstbilgiler bizde olmazdı.
- **Piksel sınırı belleğe göre:** GD piksel başına ~4 bayt; `memory_limit`
  128M → 16 Mpx (ilk yazdığım 40 Mpx ~160 MB isterdi, çekirdek düşerdi).

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| G1 | yeniden kodlama yok (özgün bayt saklanıyor) | ilk deneme kırma betiğim ikili çıktıyı okuyamayıp çöktü (KOŞULMADI); betik düzeltildi → 🔴 3 test (WebP değil, EXIF/betik kaldı) |
| G2 | tür denetimi yok | 🔴 GIF kabul edildi |
| G3 | 5 MB denetimi yok | 🔴 |
| G4 | piksel sınırı yok | 🔴 `çok piksel` (yalnız başlıklı PNG) |
| G5 | küçültme yok | 🔴 1600 testi |
| G7 | tanımda görselin uygulamaya ait olması denetlenmiyor | 🔴 |
| G8 | sonsuz önbellek üstbilgisi yok | 🔴 |
| G9 | yüklemede CSRF yok | 🔴 |

`/medya/{id}` yol kaçışı (`..%2F`) yönlendirici kalıbında (64 hex) duruyor;
serviste de biçim denetimi var (iki katman, yalnız ilki testli).

## Ölçüm
- `bin/kontrol` YEŞİL: 154 test (öncesi 141).
- **Bulgu 1 (testte):** 5 MB+1 baytlık dosya "5 MB" yerine "dosya gelmedi"
  dedi → PHP `upload_max_filesize` **2M** (konteynerde ölçüldü) uygulamaya
  gelmeden kesiyordu → `docker/myweb2wave.ini` 6M/8M, PHP'nin boyut hatası
  "5 MB" mesajına çevrildi (tuzak §31).
- **Bulgu 2 (yazarken):** 40 Mpx sınırı `memory_limit` 128M'yi aşardı → 16 Mpx.
  Test sürecinde 4000×4000 üretmek test belleğini doldurdu (süreç çekirdeği de
  taşıyor); iddia **gerçek HTTP** ile ölçüldü: 4000×4000 PNG → 201 (0,6 sn),
  4,6 MB JPEG → 201 (0,7 sn), çalışan çekirdeğe curl ile. Piksel reddi testi
  yalnız başlıklı sahte PNG ile (çözmeden önce reddedildiğini de kanıtlar).
- **Tarayıcı:** iki görsel panelde seçildi (kapak + seçenek), kaydedildi,
  `fitness v3 yayında`; huni kapak görselini temanın köşesiyle, seçenek
  görselini 48 px gösterdi. Sunulan dosya `1600x1600 VP8`, SHA-256 = kimlik,
  `Cache-Control: immutable, max-age=31536000, public`, `nosniff`.
- **Üretim imajı:** GD var, 6M/8M, `/app/var/medya` sahibi `cekirdek`.
- Yedek: `yedek.sh` görselleri tar'lıyor; dagit.sh yedeği çekirdek kalkmadan
  aldığı için `exec` değil `run --rm` (ilk kurulumda düşerdi — yazarken fark
  edildi, sunucuda koşulmadı).

## Açık
Ç44 (silme yok, alt metin yok, CDN yok, panelden dosya seçimi tarayıcıda
denenmedi) · E2.4.
