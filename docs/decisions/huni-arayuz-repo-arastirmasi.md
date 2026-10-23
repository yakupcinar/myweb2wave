# Açık kaynak huni ve anket arayüzü repo listesi — 2026-10-23

## Amaç ve yöntem

Anket/huni paneli ile ziyaretçinin gördüğü akış için görsel ve etkileşim
referansları toplandı. Yıldız ve lisans bilgisi GitHub repo sayfalarından
2026-10-23 tarihinde alındı; yıldızlar yaklaşık gösterimdir ve zamanla
değişir. Arayüz özellikleri README/dokümantasyondan toplandı; henüz repolar
çalıştırılıp elle incelenmedi. Kod kopyalanmayacak; yalnız davranış ve UI
desenleri gözlem için kullanılacak (CLAUDE.md §2.12).

## Yüksek yıldızlı adaylar

| Repo | Yıldız | Lisans | Bu proje için bakılacak UI |
|---|---:|---|---|
| [formbricks/formbricks](https://github.com/formbricks/formbricks) | ≈13 bin | AGPL-3.0 çekirdek; Enterprise dizininde ayrıca özel lisanslı kısımlar var | Anket oluşturma akışı, soru blokları, tema, yayımlama ve analiz ekranları. Ürün araştırma anketi ağırlıklı; ödeme hunisi değil. |
| [heyform/heyform](https://github.com/heyform/heyform) | ≈9 bin | AGPL-3.0 | Sohbet biçimli form oluşturma, soru/cevap ritmi, görsel tema, mantık ve sonuç analizi. Kullanıcı anketinin sade sunumuna yakın. |
| [surveyjs/survey-library](https://github.com/surveyjs/survey-library) | ≈4,9 bin | MIT (Form Library) | Ziyaretçi tarafı çok adımlı form, soru türleri, koşullu akış, gezinme ve tema. **Survey Creator sürükle-bırak editörü ticari lisans istiyor**; Creator açık kaynak referansı olarak değerlendirilmemeli. |
| [OpnForm/OpnForm](https://github.com/OpnForm/OpnForm) | ≈3,9 bin | AGPL-3.0 | Form oluşturucu, mantık, tema/özelleştirme, analitik ve sonuç yönetimi. Genel form ürünü; ödeme hunisi değil. |
| [didi/xiaoju-survey](https://github.com/didi/xiaoju-survey) | ≈3,8 bin | Apache-2.0 | Yönetim paneli ve form oluşturma, soru türü ayarları, tema, dallanma ve raporlar. Kurumsal anket aracı. |

## Huniye doğrudan yakın, yıldızı daha az adaylar

| Repo | Yıldız | Lisans | Bu proje için bakılacak UI |
|---|---:|---|---|
| [luispdoesai/openFunnel](https://github.com/luispdoesai/openFunnel) | 23 | AGPL-3.0 | Liste içindeki en doğrudan karşılık: reklam trafiğine yönelik mobil quiz hunisi, şablonlar, landing + soru + sonuç adımları, tema, görsel bloklar ve cihaz önizlemesi. Bu özellikler repo README'sinde beyan ediliyor; çalıştırıp doğrulanmadı. |
| [aialvi/openfunnels](https://github.com/aialvi/openfunnels) | 34 | MIT | Görsel sayfa editörü, sürükle-bırak, şablon seçimi, duyarlı önizleme, yayınlama ve analitik. Daha çok lead toplama/CRM hunisi. |

## Açık kaynak ölçütünü karşılamayan, ama arayüz olarak ilgili

- [baptisteArno/typebot.io](https://github.com/baptisteArno/typebot.io), ≈10,6 bin yıldız: görsel sohbet akışı ve 30+ blokla konuşma biçimli anket için yararlı UI referansı. Güncel lisansı FSL-1.1-Apache-2.0; FSL dönemi boyunca OSI açık kaynak lisansı değil, bu nedenle açık kaynak kısa listesine alınmadı. Kod alınmayacak.
- SurveyJS Creator da yıldızlı bir repo olsa da ticari geliştirici lisansı gerektiriyor; yalnız MIT lisanslı Survey Library kısa listede.

## Öncelik ve sonraki inceleme

1. Önce `openFunnel` ve `openfunnels` ekranlarına bak: ödeme dönüşüm hunisine en yakın iki örnek.
2. Sonra HeyForm ve Formbricks'te soru oluşturma, adım düzenleme, mobil önizleme ve tema akışını karşılaştır.
3. SurveyJS Library ile Xiaoju'da ziyaretçi anketinin soru, seçenek ve geçiş sunumunu incele.
4. İnceleme sonucunda yalnız bizim ürünün ihtiyacına uyan davranışları Puck paneli ve dinamik anket ekranı için planla. Repo kodunu kopyalama veya lisans varsayımı yapma.
