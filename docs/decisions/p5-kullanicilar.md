# P5 — Kullanıcılar (CRM) sayfası (2026-11-03)

Kullanıcı 2026-11-03: döngü, sıradaki görev. Plan (B5): e-posta / UTM / kampanya araması, abonelik durumu.

## Karar

- **Satır = abonelik** (`subscriptions`): durum, uygulama, maskeli e-posta, ödeme anı, güncelleme; ödemeyi başlatan ziyaretin kaynağı/ortamı/kampanyası/hunisi; reklam mı (`Rapor::REKLAM`, tek tanım — `public` oldu); kişi kaydı ve uygulamaya bağlantı.
- **E-posta maskeli** (kullanıcı kararı 2026-10-20, `panel.md`): arama tam adresle de bulur, yanıtta adres yok.
- Uç `GET /panel/api/kullanicilar?ara=&durum=` (sözleşme), en yeni güncellenen önce, en çok 200 satır; arama e-posta, `utm_source`, `utm_campaign` içinde, büyük/küçük harf fark etmez, `%`/`_` yazı olarak.
- Sayfa `/panel/kullanicilar`: arama ve durum adreste; başlığa tıklayınca istemcide sıralama (`aria-sort`). **TanStack Table eklenmedi** (planın önerisiydi): 200 satırı sıralamak için birkaç satır yetti; 8.x 18 ay eski, 9.x yeni API — bağımlılık gereksiz.
- Bulunan kusur: yeni arama yüklenirken durum seçenekleri boşalıyor, seçim kayboluyordu → `keepPreviousData`.

## Ölçüm

Gerçek yerel veri (servis doğrudan): 6 abonelik, hepsi maskeli, 1'i kaynaklı (`meta` / `ekim-test`), 2 kişi bağlı, 40 ms. Tarayıcıda denenmedi — panel girişi kullanıcının hesabı (Ç93 ile aynı).

## Kırma denemeleri (6/6 kırmızı)

K1 e-posta maskelenmez · K2 joker kaçışı yok · K3 kampanyada aranmaz · K4 reklam hep hayır · F1 aramada önceki sonuç tutulmaz · F2 ilk tıkta azalan. İlk tam koşuda test sıraya bağlıydı (başka testin bıraktığı yayın sürümüne dayanıyordu, §13) → `setUp` kendi hunisini yayınlar; K1–K4 yeniden koşuldu: yine kırmızı.

## Kontrol

`bin/kontrol` 184 s, EXIT=0, `KONTROL: YEŞİL` — core 417 test / 3.350 doğrulama, panel 102. Önceki koşu P4 grafik testinde zaman aşımıyla kırmızıydı (yavaş makinede tembel parça 1 sn'yi aştı) → testte parça önceden yüklenir.
