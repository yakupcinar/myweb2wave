# Rakip araştırması: serbest metin yanıtı — 2026-10-23

Kapsam: Web2Wave, FunnelFox ve Typeform'un kamuya açık resmi ürün belgeleri.
Panel hesaplarına girilmedi; API'lere çağrı yapılmadı. Bulunmayan saklama
süreleri çıkarım yoluyla tamamlanmadı.

## Bulgular

| Araç | Soru ve saklama/iletim | Sonuçların görünümü | Saklama süresi |
|---|---|---|---|
| **Web2Wave** | Blok listesinde `Text field` ve başka serbest giriş türleri var. Quiz cevapları kullanıcı özellikleri olarak okunup yazılabiliyor; API kullanıcı özelliklerini döndürüyor ve dış veritabanına aktarım belgesi `value TEXT` alanını, kullanıcı + özellik başına upsert modeliyle tarif ediyor. | Quiz cevap analitiği sorular için dağılım/grafik döndürüyor; ham kullanıcı özellikleri ayrı API'den okunabiliyor. Böylece agregat analiz ile kişi düzeyi veri ayrı yüzeylerde. | İncelenen belgelerde ham quiz cevapları için süre bulunamadı. |
| **FunnelFox** | `Input` öğesi serbest yazılmış metin cevabı üretiyor. Replies webhook'u onboarding tamamlanınca `state.value` içindeki kesin cevabı iletiyor; cevapları API'den istenildiği zaman çekmek de belgelenmiş. | Cevaplar (replies) analitik ve kişiselleştirme için dışarı aktarılabilir. Profil anonimleştirme API'si e-posta/telefon/parola yanında oturumdaki IP, user-agent, gönderilen form cevapları ve izleme çerezlerini temizliyor; işlem/abonelik verisini tutuyor. | İncelenen belgelerde normal yanıtlar için varsayılan süre bulunamadı. Anonimleştirme çağrısı var, otomatik saklama penceresi belirtilmiyor. |
| **Typeform** | Açık uçlu yanıtları response kayıtlarında tutuyor. Kısmi yanıtların kaydedilmesi için `Partial submit point` eklenebiliyor; bu nokta yanıt sahibinin cevaplarının kaydedilmesine onay verdiği adım olarak tarif ediliyor. Otomatik taslak yanıtları varsayılan olarak tarayıcıda 15 gün saklanıp ayardan kapatılabiliyor. | Tek tek cevaplar `Responses` içinde görülebiliyor ve tüm yanıtlar CSV/XLS olarak dışa aktarılabiliyor. Response Summary ise açık uçlu metni CSV/PDF dışa aktarımına dahil etmiyor; özet ile ham cevap ayrı tutuluyor. | 15 gün yalnız tarayıcıdaki tamamlanmamış taslaklar için. Sunucudaki tamamlanmış cevaplar için incelenen belgede genel varsayılan süre bulunamadı. |

## Ürün açısından çıkarım

1. Serbest metin, rakiplerde **gerçek yanıt** olarak saklanıp webhook/API ile
   erişilebilir; yalnızca hunide gösterilen bir metin kutusu sayılmıyor.
2. **Ham cevap ve toplu rapor ayrı tutuluyor.** Typeform özet dışa aktarımında
   açık uçlu cevapları özellikle hariç tutuyor; Web2Wave de dağılım raporunu
   kullanıcı özellikleri/veri erişiminden ayrı belgeliyor.
3. FunnelFox'un tamamlama webhook'u ve Typeform'un submit/partial submit
   noktası cevapları toplama anını belirliyor. Bu, bizim mevcut her cevap
   değişikliğini anında ilerleme ucuna yazma akışından farklı.
4. Üç ürünün kamuya açık belgelerinde bulduğum ortak bir otomatik silme süresi
   yok. Bu, süresiz saklama yaptıkları anlamına gelmez; yalnızca bu araştırma
   kaynaklarında süreyi doğrulayamadım.

## Bizim için öneri

Rakip kalıbına en yakın ve mevcut analitiği bozmayan sürüm:

- `metin` alanını isteğe bağlı soru türü olarak ekle; yanıtı seçenek cevapları
  tablosundan ayrı tut.
- Düzenleme sırasında tarayıcı durumunda tut; ziyaretçi devam düğmesine
  bastığında sunucuya kaydet ve aynı soruya geri dönüp değiştirirse son yanıtı
  koru. Serbest metni reklam olaylarına, URL'ye veya agregat cevap grafiğine
  ekleme.
- Panelde ham cevapları, seçenek sayımlarından ayrı ve erişimi korunan
  `Yanıtlar` görünümünde göster. İlk aşamada dışa aktarma ekleme; bu kapsamı
  ve veri kopyalarının kontrolünü ayrıca gerektirir.
- Yayına almadan önce sahibi saklama süresini ve yanıt ekranına erişimi
  belirlesin. Rakip dokümanları bunu bizim yerimize kararlaştırmıyor.

Bu bir ürün önerisidir; saklama süresi ve erişim yetkisi hâlâ kullanıcı kararı.

## Kaynaklar

- Web2Wave, [Quiz Content Blocks](https://docs.web2wave.com/reference/blocks-list)
- Web2Wave, [Get quiz answers analytics data](https://docs.web2wave.com/reference/get_reports-quiz-answers)
- Web2Wave, [Get user properties](https://docs.web2wave.com/reference/get_user-properties)
- Web2Wave, [Export events & user_properties to external DB](https://docs.web2wave.com/reference/export-events-user_properties-to-external-db)
- FunnelFox, [Replies export](https://docs.funnelfox.com/docs/replies-export)
- FunnelFox, [Replies in webhooks](https://docs.funnelfox.com/docs/replies-in-webhooks)
- FunnelFox, [Data deletion API announcement](https://blog.funnelfox.com/new-in-funnelfox-2026-06-02/)
- Typeform, [Response summary export](https://help.typeform.com/hc/en-us/articles/41990885069716-Export-your-Response-Summary-to-a-CSV-or-PDF)
- Typeform, [Working with responses](https://help.typeform.com/hc/en-us/articles/360029253732-Working-with-your-responses)
- Typeform, [Autosave progress](https://help.typeform.com/hc/en-us/articles/13064754462228-Enable-or-disable-autosave-progress-for-your-respondents)
