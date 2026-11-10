# P7a — Genel kabuk, tema seçici ve Aboneler (taslakla bire bir)

**Tarih:** 2026-11-10 · **İstek (kullanıcı):** P6 sonucu taslak kadar iyi olmadı; "renk, stil ve
işlevde olabildiğince birebir yap". Kararlar: tema **açık varsayılan + seçici**, sıra **genel +
Aboneler → Rapor → Uygulama → diğer sayfalar**, Rapor'daki günlük grafikler altta kalır (P7b).

## Ne yapıldı

- **Tema seçici.** Menünün altında Açık / Koyu / Sistem bölmeli seçicisi var. Seçim tarayıcıda
  saklanır (`localStorage['panel-tema']`), varsayılan Açık. Koyu renkler artık
  `:root[data-tema="koyu"]` altında ([stil.css](../../core/panel/src/uygulama/stil.css) ve
  [base.html.twig](../../core/templates/base.html.twig)); P6'daki `prefers-color-scheme` bloğu kalktı.
  Tema boyamadan önce [tema.html.twig](../../core/templates/panel/tema.html.twig) ile uygulanır (§40);
  seçim değişince [tema.ts](../../core/panel/src/uygulama/tema.ts) uygular. "Sistem" seçiliyken
  işletim sisteminin teması değişirse panel de değişir.
- **Kabuk.** Marka alanı "MyWeb2Wave / Yönetim paneli" oldu. Koyu temada kart çerçevesi kalktı
  (taslakta yok). İki yeni renk değişkeni eklendi: `--kenar-2` (#d2d2d7, denetim kenarı) ve
  `--dolgu` (#e8e8ed, bölmeli seçici zemini). İkincil düğme kenarı `kenar-2` oldu. Düğmeye iki tür
  eklendi: `yikici` (dolu kırmızı) ve `sade` (gri). Bölmeli seçici sınıfları
  [ui/bolmeli.ts](../../core/panel/src/uygulama/ui/bolmeli.ts)'de; Rapor, Yasal ve tema onu kullanır.
- **Aboneler** ([Kullanicilar.tsx](../../core/panel/src/uygulama/sayfalar/Kullanicilar.tsx)):
  - Araç çubuğu: büyüteç simgeli arama ve durum için bölmeli seçici
    (Tümü · Aktif · Denemede · Ödeme sorunlu · Bitti).
  - Sütunlar: Abone · Durum · Geldiği yer · Son ödeme · İşlem.
    - **Abone:** maskeli e-posta; altında "3 Kasım'dan beri · uygulamaya bağlı".
    - **Durum:** renkli nokta ve Türkçe, tarihli metin: "Denemede · 15 Kasım'da biter",
      "İptal edildi · 1 Aralık'a kadar açık".
    - **Geldiği yer:** kaynak ve reklam rozeti; altında kampanya · huni.
    - **Son ödeme:** "€9,99 · 3 Kas"; iade varsa altında yazar.
  - Düğmeler: "Dönem sonunda iptal et" (denemede "Deneme sonunda iptal et") ve "€6,99 iade et".
  - Onay satırın altında açılır ve sonucu söyler: "€6,99 iade edilsin mi? … 3 Kasım faturası. Geri
    alınamaz; abonelik sürer." Düğmeler Vazgeç ve kırmızı "İade et".
- **API:** `sonFatura.odendi` alanı eklendi (son faturanın ödendiği an). Sözleşme ve `uret`
  güncellendi.
- **Türkçe tarih:** [bicim.ts](../../core/panel/src/uygulama/bicim.ts)'de `kisaTarih`,
  `uzunTarih` ve `ekliTarih` var. Ek, ünlü uyumuna göre ay adından seçilir; tarih başka yıldaysa
  yıldan seçilir.

## Neden böyle

- **Tema `data-tema` + boyama öncesi betik.** Reddedilenler:
  - Yalnız React'te uygulamak: koyu seçen kişi her açılışta önce açık ekran görür (§40).
  - Sunucuda çerezle uygulamak: tek kişilik panelde fazladan istek ve kod.

  Betik iki kopya (Twig + `tema.ts`); anahtarın aynı olduğunu ve betiğin stilden önce geldiğini
  test ölçer (kural 7).
- **Marka alanında uygulama adı yok.** Taslakta "KeepChat" yazıyor; ancak bu, her sayfada fazladan
  bir istek (`/panel/api/uygulamalar` ve ayarlar) demek. Bütün sayfa testlerinin sahte fetch'i de
  değişirdi, birden çok uygulamada ise ad belirsiz kalır. Bu yüzden ürün adı + alt başlık kullanıldı.
- **"Dönem sonunda", "Ay sonunda" değil.** Taslak aylık planı örnekliyordu; yıllık planda
  "ay sonu" yanlış olur.
- **Durum süzgeci sabit beş seçenek.** Önceki açılır liste veride geçen ham durumları
  (`active`, `past_due`…) gösteriyordu. Taslak Türkçe beş seçenek istiyor. `unpaid` ve
  `incomplete` az görülür, Tümü'nde çıkar. API'nin `durumlar` alanını panel artık kullanmıyor
  (Ç128).
- **Ödeme anı yoksa tarih yazılmaz.** İlk sürüm "Ödeme bekleniyor" yazıyordu. Tarayıcıdaki gerçek
  veride `active` aboneliklerde de çıktı: ödeme anı, Stripe olayı gelmeyen yerel veride boş. Bu
  yanlış bir iddia, kaldırıldı; test boş alt satırı ölçer.
- **İptal planı + durumsuz satır.** Yerel veride `status` boş, `iptal_donem_sonu` true olan bir
  satır "Bekliyor" ve "İptali geri al" gösteriyor. Önceki davranış korundu (Ç129).

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| K1 | İptal planı durumdan önce söylenmez | 1/8 düştü |
| K2 | Denemede düğme "Dönem sonunda" | 1/8 |
| K3 | Vazgeç onayı kapatmaz | 1/8 |
| K4 | İade düğmesi kalan yerine tam tutar | 2/8 |
| K5 | "Son ödeme" sıralaması fatura yerine ilk ödemeye göre | 1/8 |
| K6 | Durum seçici adrese yazmaz | 1/8 |
| K7 | Kasım eki yanlış (-den) | 1/4 |
| K8 | Yıl eki (5 → -den) | 1/4 |
| K9 | "Sistem" işletim sistemini yok sayar | 1/4 |
| K10 | Sistem dinlemesi bitmez | 1/4 |
| K11 | Seçim saklanmaz | 1/4 |
| K12 | API `odendi` boş | PanelAbonelikTest 1/6 |
| K13 | Tema betiği stilden sonra | PanelRenkleriTest 1/5 |
| K14 | Koyu tehlike rengi okunaksız (#b03a35) | PanelRenkleriTest 2/5 |
| K15 | Ödeme anı yokken "Ödeme bekleniyor" (tarayıcıda bulunan) | 1/8 |

15/15 kırmızı; her denemeden sonra dosya geri alındı (`git status` yalnız amaçlanan değişiklikler).
Denemeler `vitest` ile koştu (tip denetimi yok). Yamalar geçerli TypeScript; sahte kırmızı yok (§8).

## Ölçüm

- `bin/kontrol` YEŞİL: çekirdek 737 test; panelde Kullanicilar 8 ve Duzen 4 yeni/güncel test.
- Tarayıcı (kullanıcının oturumu, `localhost:8081/panel/kullanicilar`, derlenmiş panel):
  - Taslağın düzeni ve renkleri ekranda.
  - Koyu seçilince `body` zemini `rgb(0, 0, 0)`; yeniledikten sonra `data-tema="koyu"`, seçici
    "Koyu" basılı (saklandı).
  - Onay satırı açıldı; Vazgeç ile kapandı, istek gitmedi.
  - Sonra tema Açık'a geri alındı.
- `curl /panel/giris`: tema betiği (satır 10) `<style>`'dan önce (satır 17).
