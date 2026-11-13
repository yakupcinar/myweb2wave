# P7d — Entegrasyonlar sayfası (A25, 2026-11-11)

## Ne yapıldı

Menüdeki Webhook'lar, API anahtarları ve Ürün bağlamı (AI) yerine tek **Entegrasyonlar** sayfası geldi
(`/panel/entegrasyonlar`). Sayfada üç bölüm var: `#webhooklar` · `#anahtarlar` · `#baglam`. Her bölüm 20 px başlık,
kısa açıklama ve kartlardan oluşuyor, Uygulama sayfasıyla aynı dilde (`ui/Bolum`, `ui/Alan` `Satir`).

- **Webhook'lar:**
  - Olaylar Türkçe adlarıyla üç grupta seçilir: Huni · Ödeme · Abonelik.
  - Birden çok olayı olan grupta "Tümü" kutusu var; yarım seçimde belirsiz görünür.
  - Sözlükte olup panelde adı olmayan olay "Diğer" grubunda teknik adıyla çıkar.
  - Hedef satırında olaylar özetlenir ("Ödeme: tümü"); teslim günlüğünde de olayın Türkçe adı var.
  - Silme onayında dolu kırmızı düğme var.
- **API anahtarları:**
  - Listede yalnız etkin anahtarlar durur.
  - İptal edilenler katlanır "İptal edilenler (N)" bölümünde; oradan iki adımlı onayla ("Kalıcı silinsin mi? Geri
    alınamaz.") kalıcı silinir.
  - Yeni uç: `DELETE /panel/api/uygulamalar/{app}/anahtarlar/iptal-edilenler/{id}`. Yalnız iptal edilmiş ve bu
    uygulamaya ait anahtarı siler, yoksa 404 döner. Anahtarın `mcp_cagrilari` satırları da aynı işlemde silinir.
- **Ürün bağlamı:** satır düzenine geçti (solda etiket); karakter sayacı yardım satırında, Kaydet sağ altta.
- **Eski adresler:** `/panel/webhooklar`, `/panel/anahtarlar` ve `/panel/baglam` sunucuda 301 ile ilgili `#bölüm`'e
  gider. Sayfa çizildikten sonra o bölüme kaydırır, çünkü tarayıcı ilk yüklemede bölümü henüz bulamaz.

## Neden böyle

- **Tek sayfa, üç bölüm:** kararı kullanıcı verdi (A25). Reddedilen seçenekler: üçünün ayrı kalması ve Ürün
  bağlamı'nın ayrı kalması.
- **Silme için ayrı uç:** mevcut `DELETE …/anahtarlar/{id}` iptal ediyor. Bu ucun anlamını "sil"e çevirmek, onu
  çağıran her yerin davranışını sessizce değiştirirdi. `iptal-edilenler/{id}` yolu, yalnız iptal edilmiş anahtarın
  silinebildiğini adının içinde söylüyor.
- **Çağrı kayıtları da silinir:** `mcp_cagrilari.anahtar_id` yabancı anahtarı NOT NULL ve tablo hiçbir yerde
  okunmuyor (yalnız yazılıyor; testler okuyor). Reddedilen seçenek: kayıtları sahipsiz bırakmak. Bunun için göç
  gerekirdi ve ortada okuyan bir şey yokken anlamsız bir geçmiş kalırdı. Etkin anahtar silinemez; tek tıkla kalıcı
  kayıp olmasın (A25).
- **Türkçe adlar panelde:** sözlük çekirdekte. Adlar sunucu yanıtına eklenseydi sözleşme ve iki istemci değişirdi.
  Eşliği `PanelOlayAdlariTest` ölçer: sözlükteki her olayın panelde tam bir kez adı var ve panelde fazladan ad yok
  (kural 7).
- **Eski adres sunucuda yönlenir:** yer imleri bozulmuyor. İstemci yönlendirmesi yalnız uygulama açıkken çalışırdı;
  doğrudan adres yine §51'e takılırdı.
- **Dosyalar yeniden adlandırılmadı:** `Webhooklar.tsx`, `Anahtarlar.tsx` ve `Baglam.tsx` artık birer bölüm dışa
  veriyor; `Entegrasyonlar.tsx` bunları dizer (kural 5). Bölüm bileşeni ortak `ui/Bolum` oldu; Ayarlar sayfası da onu
  kullanıyor. Rapor'un kendi `Bolum`'ü değişmedi.

## Kırma denemeleri — 19/19 kırmızı (+1 koşulmadı)

| Deneme | Sonuç |
|---|---|
| B1 etkin anahtar da silinir | 1 test düştü |
| B2 başka uygulamanın anahtarı silinir | 1 |
| B3 çağrı kayıtları silinmez (yabancı anahtar) | 1 |
| B4 tüm çağrı kayıtları silinir | 1 |
| B5 silmede CSRF yok | 1 |
| B6 eski adres hep `#webhooklar` | 1 |
| B7 bir olayın Türkçe adı eksik | 1 (`PanelOlayAdlariTest`) |
| B8 "Diğer" satırı silindi | 13. Çok geniş: `if`'in gövdesi `return`'e kaydı; yerine B8b koştu |
| B8b "Diğer" hiç eklenmez (`if (false)`) | 2 |
| B9 özette "tümü" hiç yok | 1 |
| B10 yarım seçim gösterilmez | 1 |
| B11 tek olaylı grupta da "Tümü" | 1 |
| B12 "Tümü" yalnız bırakır | **KOŞULMADI**: metin iki kez geçiyordu; yerine B12b ve B12c koştu |
| B12b "Tümü" önceki seçimi siler | 1 |
| B12c "Tümü" hiçbir şey seçmez | 1 |
| B13 iptal edilenler etkin listede | 2 |
| B14 iptal edilenler hep açık | 1 |
| B15 Sil onaysız | 1 |
| B16 `#bölüm`'e kaydırma yok | 1 |
| B17 menüde eski bağlantı | 1 |

Betikler: `$TMPDIR/…/scratchpad/kir-p7d.py` ve `kir-p7d-2.py`.

## Ölçüm

- **Testler:**
  - Çekirdekte yeni olanlar: anahtar silme, eski adres yönlendirmesi, olay adı eşliği.
  - Etkilenen 10 süit birlikte 106 testte yeşil. Kabuk jetonunu `/panel/anahtarlar`'dan alan 10 test dosyası
    `/panel/entegrasyonlar`'a taşındı.
  - Panel: 175 test (yeni olanlar: Tümü / Diğer, özet, iptal edilenler + silme, `#bölüm`'e kaydırma).
- **Tam kontrol:**
  - İlk koşu KIRMIZI çıktı: PHPStan testte `array_map(intval(...))`'i karışık tipte buldu. Testler yeşildi.
  - SQL'de `::int`'e çevrildi; B3 ve B4 yeniden koşuldu ve yine kırmızı çıktı.
  - İkinci koşu YEŞİL, 748 çekirdek testi.
- **Geliştirme ortamında:** dört adres de girişsiz `302 → /panel/giris` döndü (girişli yönlendirmeyi test ölçüyor).
  Yerelde 1 etkin + 6 iptal anahtar var; sayfada "İptal edilenler (6)" görünmeli.
- **Tarayıcıda: KOŞULMADI.** Chrome eklentisi bağlı değil. P7b ve P7c ile birlikte bekliyor.
