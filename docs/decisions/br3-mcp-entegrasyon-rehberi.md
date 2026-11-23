# BR3 — MCP aracı `entegrasyon_rehberi` (2026-11-23)

BR'nin üçüncü yüzü: uygulamanın kod tabanında çalışan yapay zekâ, bağlantı talimatını panelden kopyalamadan MCP'den
alır (BR2 ile aynı metin, BR1 ile aynı durum).

## Karar

- Araç `RaporAraclari`'nda: **salt okur** (`readOnlyHint`), **her anahtarda** görünür, girdi yok; uygulama anahtarın
  uygulaması. Yanıt `{metin, durum}` — `metin` = `EntegrasyonRehberi::metin($app)`, `durum` = `BaglantiDurumu::durum($app)`.
  İkisi de mevcut sınıftan; MCP'de metin yeniden kurulmaz (tek kaynak).
- MCP sunucu talimatına tek cümle: "çekirdeğe bağlantı kuruyorsan önce `entegrasyon_rehberi`" — araç adı talimatta
  geçmezse uygulama deposundaki yapay zekâ onu huni araçları arasında görmeyebilir.
- Durumda gizli ya da kişisel değer yok (yalnız tamam/sayı/zaman); metinde anahtar yer tutucu (BR2).
- Reddedilen: `YonetimAraclari` (yönetim izni ister; bağlantı talimatı okumak yazma değildir) · yeni sınıf (tek araç
  için ek kablolama) · ayrı `baglanti_durumu` aracı (metin eksikleri zaten içeriyor; istek "aynı metin + durum" tek araç).

## Test ve kırma

`McpTest::testEntegrasyonRehberiPanelMetniVeDurum` (+ araç listesi iddiası). Kırma 5/5 kırmızı:
K1 metin başka uygulamanın (`'baska'`) · K2 durum başka uygulamanın · K3 metin panelden farklı (sona `.`) ·
K4 araç yalnız yönetim anahtarında · K5 talimattan araç adı çıkar. K5'i ölçen iddia ilk yazımda yoktu; kırmadan önce
eklendi (initialize yanıtının `instructions`'ı).

## Ölçüm

- `bin/kontrol` EXIT=0 (2026-11-23, yerel macOS + Docker, 7 dk 38 sn; çekirdek 833 test).
- Gerçek HTTP (yerel `localhost:8081/mcp`, dev DB, geçici okur anahtarı — ölçümden sonra iptal edildi):
  initialize talimatında araç adı var · `tools/call entegrasyon_rehberi` `isError=false`, durum 6 alan, metin KeepChat
  başlığıyla · dev `UYGULAMA_ANAHTARI_KEEPCHAT` değeri yanıtta 0 kez.
- Canlıda ölçülmedi (dağıtım sonrası `/health` sürümüyle).
