# Ç152 — Panel yazma denetimi: her panel yazmasının izi (2026-11-19)

Kullanıcı: "başka taskla devam et durma" (2026-11-19); Ç152'de önerilen genişletme uygulandı. Neden: Ç151 yalnız
abonelik işlemlerini tutuyordu; API anahtarı (yönetim izni), fiyat, huni yayını, webhook, Meta/TikTok anahtarı,
yasal metin ve e-posta şablonu değişiklikleri iz bırakmıyordu.

## Karar

- **Tek dinleyici, rota başına kod yok** ([`PanelDenetimi`](../../core/src/Panel/PanelDenetimi.php), `kernel.response`):
  `/panel` altındaki her `POST/PUT/PATCH/DELETE` yazılır — kim, yöntem, rota adı, kaynak (rota parametreleri:
  `app`, `id`, `anahtar`…), HTTP durumu. 45 panel yazma rotasının hepsi (ölçüm: `debug:router`), sonradan eklenen
  uç da kendiliğinden. **Reddedilen:** her denetleyiciye `yaz()` çağrısı (Ç151 gibi) — 45 yerde unutulur, kural
  ailenin tamamına yazılmaz.
- **Gövde yazılmaz:** anahtar, sır, metin istekte; iz yalnız neyin nereye yapıldığını söyler, ne yazıldığını değil.
  Değişikliğin içeriği gerekirse ilgili tablonun kendi geçmişinde (huni sürümleri). **Reddedilen:** gövdeyi
  maskeleyerek yazmak — her uç için ayrı maske listesi, birinde unutulan alan sır sızdırır.
- **Yalnız oturum açmış kullanıcı:** oturumsuz istek hiçbir şey değiştiremez (güvenlik duvarı); yazılsaydı tablo
  taramayla dolardı (panel hız sınırından muaf). Sonuç: başarısız giriş denemeleri izde yok (aşağıda açık).
- **Reddedilen deneme de yazılır** (403 jeton, 422 doğrulama): Ç151'deki "yapıldı / reddedildi" ile aynı gerekçe.
- Göç `Version20261119090000`: `panel_denetim`, yöntem `CHECK`, `BEFORE UPDATE OR DELETE` tetikleyicisi (Ç151 gibi).
- Okuma: `bin/console app:panel-denetim [--son N]` (1–1000), satır başına
  `zaman UTC · kullanıcı · YÖNTEM rota {kaynak} → durum`. Panel sayfası yok (aşağıda açık).
- Ç151'in `panel_islemleri` tablosu kalır: abonelik geçmişi panelde görünüyor ve reddin metnini tutuyor; abonelik
  işlemleri artık ikisine de yazılır.

## Testler ve kırma

[`PanelDenetimTest`](../../core/tests/Controller/PanelDenetimTest.php) 6 test: kim/rota/kaynak/durum (ekle 201, sil
204) · reddedilen (422, 403) ve `/panel/api` dışı yazma (`panel_huni_taslak`) · gövde (CAPI anahtarı ve Pixel ID
izde yok) · okuma ve oturumsuz istek yazılmaz · iz değiştirilemez · komut sırası ve sınırı. İlk koşuda kaynak
anahtar sırası farklı geldi (tuzak §28) — testte `ksort`.

**Kırma 8/8 kırmızı:** K1 GET de yazılır (tüm testler: göçteki `CHECK` GET'i reddediyor — iki katman) · K2 yalnız
`/panel/api` (taslak ucu kayboldu) · K3 gövde rotaya eklendi · K4 yalnız başarılı · K5 kaynak boş · K6 komut eskiden
yeniye · K7 tetikleyici düşürüldü · K8 oturumsuz da yazılır. İlk kırma turundan önce K2'yi ölçen test yoktu —
`/panel/api` dışı yazma testi o yüzden eklendi.

## Açık kalanlar

- Panelde görünür denetim sayfası yok; okuma sunucuda komutla.
- Başarısız panel girişi izde yok (oturumsuz); giriş saldırısını görmek gerekirse ayrı ve hız sınırlı kayıt.
- MCP yönetim araçları (iptal planlama, iade, deney başlat/durdur — `/mcp`) bu izde değil; kimlik API anahtarı.
