# T1a — ekran adımı ve blok kataloğu

Kullanıcı 2026-11-02: "blok kataloğu ile devam et". Plan: T1 (temel 1) "ekran = blok listesi + blok kataloğu — AI'ın üretebildiği kendiliğinden büyür". Kaynak bulgu: `web2wave-platform-yapisi.md` (web2wave ekranı blok listesi; bizde adım = tek tür, AI'ın tavanı sözleşme).

## Karar (ilk dilim)

- Yeni adım türü **`ekran`**: `baslik` (sayfanın h1'i, odak oraya) + `bloklar` (1–12) + `dugme`, isteğe bağlı `hedef` (yalnız ileri, tanıtım gibi) ve `ilerlemeGoster`.
- Blok kataloğu (7): `baslik` (ara başlık h2), `metin` (paragraf), `gorsel` (+ `gorselMetni`), `maddeler` (1–6), `yorum` (metin, yazar, isteğe bağlı unvan, puan 1–5 — yıldız + ekran okuyucuya "5 üzerinden N puan"), `istatistik` (değer ≤ 12 + metin), `kutu` (vurgulu not, isteğe bağlı tek emoji).
- Tek kaynak: sözleşme `EkranAdimi`/`EkranBlogu` → TS tipleri + AI araç şeması üretilir; çekirdek `Tanim::BLOKLAR` (tür → zorunlu/isteğe bağlı alan → uzunluk); blok görselleri medya denetimine girer.
- Eski türler yerinde (kırılan tanım yok); taşınması ve soru bloğu sonraki dilim (Ç83).
- Bloklar `div.bloklar` içinde: Puck yuvası (slot) `Render`'da da aynı öğeyi üretiyor (ölçüldü: `<div class="bloklar">`) → iki yolda DOM aynı.
- Puck: `Ekran` bileşeni yuvalı (`allow` = 7 blok bileşeni), bloklar ayrı kategoride. Kök bölge kısıtlanamıyor → dışarıdaki blok için düzenleyici uyarısı (`disaridakiBloklar`), kayıtta türüyle gider ve çekirdek reddeder (sessizce düşmez, ödemeye dönüşmez).
- AI: `Tanim::EKRAN_YONERGESI` iki istemde (panel + MCP) aynı metin; istatistik yalnız ürün bağlamında verilen gerçek sayıyla, yorum bloğu "Örnek —" etiketiyle (yanıltıcı reklam).

## Tasarım değişikliği (yolda)

İlk sürümde h1 = "ilk başlık bloğu" idi (en az bir başlık bloğu kuralıyla). Puck'ta blok bileşeni yuvadaki sırasını bilmiyor; önizleme huniden farklı çizerdi. Başlık ekranın alanına taşındı (Ç82 onay).

## Ölçüm

- Gerçek sistem: KeepChat `genel` taslağına (önce yedek) 5 bloklu ekran yazıldı (`taslak_kaydet` geçti), `ekran_goruntusu` ile çekildi: bloklar sırayla, tema renkleri, emoji renkli. Kusur görüldü: ilk blok başlığa yapışık (8 px) → `.bloklar` üst boşluğu, yeniden çekildi. Taslak geri yüklendi: tanım ve imza yedekle AYNI.
- Yan bulgu (§38 yeniden): `huni-dev` 5 saattir eski kodu sunuyordu (`Yükleniyor…`'da kaldı); `docker restart huni-dev` sonrası çizdi.
- Tarayıcıda Puck düzenleyicisi ölçülmedi (Ç83 (4)).

## Kırma denemeleri (17/17 kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `ekran` türü TURLER'den çıkar | 🔴 testEkranAdimi |
| K2 | blok alan kümesi denetlenmez | 🔴 blok fazla alan |
| K3 | puan sınırı 6 | 🔴 blok puan 6 |
| K4 | kutu simgesi denetlenmez | 🔴 kutu simgesi harf |
| K5 | blok görseli medya denetimine girmez | 🔴 testEkranAdimi |
| K6 | blok sınırı 13 | 🔴 ekran 13 blok |
| K7 | oynatıcı ekran hedefini yok sayar | 🔴 huni testi |
| K8 | ara başlık h1 | 🔴 2 bileşen testi |
| K9 | `div.bloklar` yok | 🔴 3 bileşen testi |
| K10 | akış denetimi ekran hedefini bilmez | 🔴 akis |
| K11 | dışarıdaki blok uyarısı yok | 🔴 akis |
| K12 | boş unvan tanıma yazılır | 🔴 donustur |
| K13 | kökteki blok ödemeye dönüşür | 🔴 donustur |
| K14 | MCP isteminde blok yönergesi yok | 🔴 McpTest |
| K15 | panel isteminde blok yönergesi yok | 🔴 PanelAiTest |
| K16 | blok paragraf kuralı yok | 🔴 bileşen CSS |
| K17 | Puck yuvasının sınıfı yok | 🔴 onizleme (Render) |

Ortam: yerel Docker, macOS; her deneme dosyayı yedekleyip geri yükledi.

CI (bağımlılıklar kilit dosyasından sıfırdan): koşu 41429768805, SHA 3e7efce, `KONTROL: YEŞİL` (core 339 test, panel + huni testleri dahil).
