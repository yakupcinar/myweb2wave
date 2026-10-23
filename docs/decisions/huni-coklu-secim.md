# E2.4b — huni sorusunda çoklu seçim — 2026-10-22

## Ne yapıldı

Puck soru ayarına “Tek seçim / Çoklu seçim” ve çoklu mod için düzenlenebilir
devam düğmesi metni eklendi. Tekli sorular seçeneğe dokununca eskisi gibi ilerler.
Çoklu soruda yanıtlar seçilip kaldırılabilir; devam düğmesi boş seçimde kapalıdır,
seçim durumları erişilebilir `aria-pressed` niteliğiyle bildirilir. Seçenek
sırası/kimliği korunarak cevaplar API'ye dizi halinde gönderilir. Paneldeki mevcut
cevap raporu her seçeneği ayrı sayar. Tekli modda seçenek başına dallanma sürer;
çoklu modda hedef alanı gizlenir ve çekirdek hedef içeren tanımı reddeder.

API eski tekil `cevap` alanını kabul etmeyi sürdürür. Çoklu yanıtlar `cevaplar`
listesiyle gider; iki alan aynı istekte kullanılamaz. Her (ziyaret, soru, seçenek)
çifti ayrı satırdır. Aynı soru yeniden gönderilince önceki yanıt kümesi tek
transaction içinde silinip son seçimler yazılır. Yeni göç eski cevap satırlarını
koruyarak birincil anahtarı genişletir; geriye alma, bir soruda birden çok seçim
varsa veri kaybını önlemek için durur.

## Neden böyle

- Çoklu yanıtı tek metinde birleştirmek raporlamayı ve tekrar gönderimde seçenek
  bazında sayımı zorlaştırır; seçenek başına satır doğal ölçüm birimidir.
- Çoklu seçenekte farklı hedefler çelişir. İlk seçilene ya da son seçilene göre
  dallanmak tahmin edilemez olur; bu modda seçenek dallanması kapalıdır.
- Tekli modun API gövdesi korunur; eski üretilmiş huni kodu yeni özelliği
  kullanmasa da mevcut cevap kaydı çalışır.

## Kırma denemeleri

| Bozulan | Sonuç |
|---|---|
| Çoklu soruda hedef reddi kaldırıldı | 🔴 `testCokluSecimdeSecenekHedefiKullanilamaz` |
| Cevap güncellenirken önceki seçenekleri silme kaldırıldı | 🔴 çoklu cevap güncelleme testi; eski `aile` seçimi kaldı |
| Çoklu seçimden devam adımı kaldırıldı | 🔴 oynatıcı testi, soru ekranından ilerleyemedi |
| Editörde çoklu mod görünürlüğü sabit kapalı yapıldı | 🔴 `yapilandirma.test.ts` görünürlük testi |

İlk oynatıcı kırma yaması girintiyle eşleşmedi; kod değişmedi ve testin o
koşusu **KOŞULMADI** sayıldı. Doğru satır doğrulanıp deneme tekrarlandı ve
kırmızı görüldü (tuzak §8).

## Ölçüm

- `bin/kontrol`: EXIT=0. Çekirdek 191 test / 1184 assertion; editör 18 test;
  ortak bileşenler 20 test; huni 37 test. Biçim, statik analiz, tip kontrolü,
  Astro/editör derlemeleri ve JS boyut kapısı geçti.
- Çoklu yanıt API/DB hedefli testleri: HuniTest + AtifTest + PanelTest içinde
  89 test / 552 assertion geçti; panel raporunda aynı ziyaretin iki seçeneği
  iki ayrı cevap sayımında göründü.
- `Version20261022120000` yerel geliştirme DB'sine uygulandı. `/health`, panel
  giriş sayfası ve huni sayfası HTTP 200 döndürdü.
- Work ayarlarında yerel tarayıcı otomasyonu kapalı. Bu yüzden Puck alanlarını
  tarayıcıda elle görsel olarak kontrol edemedim; dinamik alan görünürlüğü,
  önizleme DOM'u ve çoklu yanıt akışı testlerle doğrulandı.

## Açık

Sosyal kanıt/yorum ve serbest metin gibi diğer soru türleri ayrı özellikler
olarak bekliyor. Çoklu soruda seçenek dallanması bilinçli olarak desteklenmiyor.
