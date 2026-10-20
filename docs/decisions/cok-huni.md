# Blok: E2.1 — çok huni + yönlendirme kuralları (A8) — 2026-10-20

## Ne yapıldı
Bir uygulamanın birden çok hunisi var (`huniler (app, ad)`, tam biri
varsayılan — kısmi tekil dizin). Var olan huni `genel` adını aldı ve
varsayılan oldu (göç). `huni_kurallari`: sıralı; adresteki parametre
(`k`, `utm_source`, `utm_campaign`, `utm_content`) = değer → huni; ilk tutan
kazanır, hiçbiri tutmazsa varsayılan; yayınlanmamış huniye giden kural
atlanır; `?huni=<ad>` doğrudan test adresi. Seçimi çekirdek yapar
(`GET /huni/{app}?k=…` → `{huni, surum, tanim}`); huni sayfası YALNIZ bu
parametreleri gönderir. Ziyaret `huni` + `surum` taşır (yayınlanmış çift
değilse ikisi de null). Önizleme anahtarı huniye de bağlı. Panel:
`/panel/huniler` — huniler (Düzenle, Varsayılan yap), "Huni aç" (boş ya da
kopya), kural tablosu (ekle, sil, yukarı/aşağı); düzenleyici
`/panel/huniler/{app}/{ad}`. Rapor: huni sütunu, adım sayımı huni başına.
Tema ve görseller sonraki bloklar (E2.2, E2.3).

## Neden böyle
- **A8** (kullanıcı): tek adres + kurallar; içerik, tema, görsel huni başına.
- **Seçim çekirdekte:** kurallar orada; tarayıcıda seçim kuralları
  istemciye sızdırır, iki kaynak olur.
- **Yalnız seçim parametreleri gider:** `fbclid` her tıklamada farklı —
  tanım isteğine girseydi 60 sn önbellek hiç tutmazdı (ağda ölçüldü: istek
  `?k=FITNESS&utm_source=meta`, `fbclid` yok).
- **Değer küçük harfle saklanır** (DB kısıtı da), karşılaştırma büyük/küçük
  harf duyarsız: reklam platformları kampanya adını farklı yazabilir.
- **Kural yayınlanmamış huniyi gösterirse atlanır:** yarım huni yayına çıkmaz.
- Reddedilenler A8'de.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| S1 | `?huni=` doğrudan adres yok sayılıyor | 🔴 `testDogrudanHuniParametresiTestAdresi` |
| S2 | büyük/küçük harf duyarlı | 🔴 `testKuralEslesirse…` |
| S3 | yayınlanmamış huniye giden kural atlanmıyor | 🔴 `testYayinlanmamisHuniyeGiden…` |
| S4 | kurallar ters sırada | 🔴 `testIlkTutanKuralKazanir…` |
| S6 | tek-varsayılan dizini düşürüldü (test DB) | 🔴 `testVarsayilanDegisirTekVarsayilanKisittir`; dizin geri kondu |
| S7 | ilk huni varsayılan olmuyor | 🔴 7 test |
| S8 | ziyaret hunisi denetlenmeden yazılıyor | 🔴 yabancı anahtar → 500 |
| S9 | önizleme anahtarı huniye bağlı değil (iki taraftan) | 🔴 `testOnizlemeAnahtariHuniyeBagli` |

## Ölçüm
- `bin/kontrol` YEŞİL: 117 test / 722 doğrulama (öncesi 105).
- Göç dev veritabanında: `genel` varsayılan, eski 6 sürümlü ziyaretin 6'sı `huni = genel`.
- **Tarayıcı (yerel):** panelden `fitness` = `genel`'in kopyası açıldı,
  başlığı değiştirildi, kaydedildi, `fitness v1 yayında`; kural `k = Fitness`
  → `k=fitness` olarak saklandı. Aynı adres: `?k=FITNESS&…&fbclid=…` →
  "Fitness ekibin için KeepChat"; etiketsiz → "KeepChat'e hoş geldin".
  DB: ziyaretler `fitness/1` ve `genel/5`.
- **Araç yanılgısı (tuzak §11):** tarayıcı aracının ağ kaydı `/ilerleme`
  POST'larını **503** gösterdi; curl (Origin'li ve Origin'siz) 204, çekirdek
  günlüğü rotayı karşıladı, DB'de adım satırları var — 503 aracın raporu.
- **Gözlem:** paneldeyken bir POST beni girişe attı; curl'le aynı akış
  sorunsuz. PHP oturumu `gc_maxlifetime = 1440` sn, temizlik 1/100 olasılıkla
  (ölçüldü) — 24 dk'dan uzun boşta kalan panel oturumu rastgele düşebilir (Ç41).
- Tarayıcı aracında birkaç tıklama sayfaya ulaşmadı; koordinatla tekrarlandı.

## Açık
Ç41 (panel oturum ömrü örtük 24 dk; huni silme/yeniden adlandırma yok) ·
E2.2 tema · E2.3 görseller · E2.4 sürükle-bırak · Ç39.
