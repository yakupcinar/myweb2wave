# AI tema yönergesi (Ç71 (a)) — 2026-10-27

**Sorun (kullanıcı):** Claude ile kurulan huniler görsel olarak birebir aynı — renk ve yazı farkı yok.
**Ölçüm (kod okuması):** panel asistanının sistem istemi ve MCP talimatı temadan hiç söz etmiyordu; tema isteğe bağlı → AI koymuyor → varsayılan görünüm (`#2563eb`, beyaz, sistem yazı tipi).

## Karar
- `Tema::aiYonergesi()` tek kaynak (kural 7: iki istemci aynı kural): taslakta tema yoksa ya da varsayılansa ürün bağlamına (kategori, kitle, ton) uyan tema seç; yöneticinin seçtiği temaya istenmedikçe dokunma; alanlar, yazı tipleri, köşe sınırı ve kontrast eşiği `Tema` sabitlerinden üretilir (elle kopya yok).
- `Tema::AI_ORNEKLERI`: 5 örnek tema (sağlık, teknoloji, finans, eğlence, iletişim) — "aynen kopyalama, uyarla".
- Ekleyen yerler: `HuniAsistani::sistemIstemi` (önbellekli sabit blok — değişmiyor), `McpSunucusu::talimat`. A4 bağlam başlığı: "metinleri ve görünümü (tema) buna göre seç".
- Reddedilen: AI'ın tema seçmesini zorunlu kılmak (tanımda `tema` zorunlu) — eski tanımları ve "temasız" bilinçli tercihi bozardı; sunucuda kategoriye göre otomatik tema — yöneticinin görmediği sihir, AI zaten bağlamı okuyor.
- Yan etki: tema yoksa panel asistanı yalnız metin istendiğinde de tema ekler (yönerge böyle) — istem "istenmeyen adımlara ve metinlere dokunma" der, tema bunların dışında.

## Ölçüm
- Testler: `TemaTest::testAiOrnekTemalariGecerliVeFarkli` (örnekler `Tema::hatalar` → [], ana renkler birbirinden ve varsayılandan farklı, ≥ 3 yazı tipi; yönerge metninde elle yazılmış parçalar — §3), `PanelAiTest::testUrunBaglamiSistemIstemine`, `McpTest::testElSikismaAraclarVeTaslagaYazma`.
- Kırma 6/6 kırmızı: K1 yönerge boş (yalnız TemaTest yakalar — diğer ikisi boş dizgeyi "içerir" sayardı; bu yüzden bağımsız parça denetimi var) · K2 panel istemine eklenmez · K3 MCP talimatına eklenmez · K4 örnek kontrastı düşük (#1e293b / #0b1120) · K5 bağlam başlığı eski · K6 örnek ana rengi varsayılan.
- `bin/kontrol` yeşil (268 çekirdek testi). Bir ara PHPStan "her zaman doğru" dedi (sabit dizide `assertNotSame`) → farklılık tekillik denetimine katıldı.
- **Ölçülmedi:** gerçek Claude'un artık farklı temalar seçtiği. Panel AI anahtarı yok; MCP'ye canlı Claude bağlanmadı. Ölçüt: aynı uygulama için iki farklı bağlamla huni iste → `tema` alanı dolu ve farklı mı (A6 değerlendirme setine aday).
