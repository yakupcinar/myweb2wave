# 3 · Araştırma ve karar

## Karar kaydı: ne · neden · reddedilen · tarih

Her karar `docs/pre-setup.md`'ye yazılır (§A ürün, §B yığın, §C araç).
**Reddedilen seçenekler gerekçesiyle** yazılır: altı ay sonra o seçeneği
yeniden öneren kişi büyük ihtimalle biz olacağız. Karar değişirse eskisi
**silinmez, üstü çizilir**; yenisi altına gerekçesiyle (örnek: B1 tek
Next.js → B2 Astro + Symfony).

Karar kullanıcıya aitse ajan **seçenekleri ve önerisini** sunar, kararı
kullanıcı verir; ajan yalnız ölçülebilir olguları kendi karar verir (ör.
hangi Docker etiketi). Kararın sahibi kayıtta yazar ("kullanıcı onayı").

## Açık uçlar: `docs/decisions/acik-uclar.md`

"Fark ettim ama karar vermedim" cümlesinin tek yeri. Her satır: Ç-no,
gözlem, kaynak, **tetikleyici** (ne zaman yeniden bakılacak). Kapanınca
silinmez: `~~Ç-no~~ ✅ KAPANDI <tarih>` + kararın yeri. Bağlı uçlar iki
yönde bağlanır (Ç28 ↔ Ç12). Kapanış mesajındaki her "fark ettim" cümlesinin
depodaki karşılığı `grep` ile gösterilir.

## Kaynak seçimi: ezbere değil, ölçerek

| Ne | Yöntem | Örnek |
|---|---|---|
| Sürüm | kayıt deposundan oku; **≥2 haftalık**, `.0`'dan kaçın, **LTS** tercih | Symfony 7.4 LTS (8.1 değil), Astro 7.3.8 (7.4.0 değil) |
| Uyumluluk | eş bağımlılığı (peer) oku, uyarıyı bastırma | `@astrojs/check` TS 7'yi desteklemiyor → TS 6; üretici ayrı pakette TS 5 |
| Docker imajı | etiket + **digest**'e sabitle | etiketler yeniden derleniyor |
| Bakım | son sürüm tarihi, arşiv durumu | **ölçüm aracını da doğrula**: `x.y.z` filtresi `0.24`'ü kaçırdı, bakımlı paket bakımsız göründü |
| SDK var mı | sağlayıcının resmi deposu | iyzico resmi SDK yalnız PHP → B2'de Symfony; Apple sunucu kütüphanesi PHP'de yok (Ç29) |

## Lisans: kaynak başına ölçülür (kural 12)

AGPL/GPL bir depodan tek satır kopyalamak projenin tamamını o lisansa
çeker. Her kaynağın lisansı ayrı ölçülür
(`gh api repos/<r> --jq .license.spdx_id`); lisansı **olmayan** depo "tüm
hakları saklı" sayılır — yalnız davranış okunur, kendi cümlemizle yazılır.

## Rakip araştırması: kara kutu gözlem (C4)

- **Serbest:** herkese açık sayfa, HTTP başlığı, belge, SDK'nın davranışı,
  deneme hesabıyla huniyi müşteri gibi yürümek.
- **Yasak:** bot korumasını aşmak (web2wave paneli 403 → bırakıldı),
  panele/API'ye yetkisiz istek, derlenmiş kodu açmak, kaynağı belirsiz
  "yeniden yapım" klonları.
- Bulgu dışarıdan ölçülebilir: web2wave'in yığını çerez biçiminden
  (Laravel'in şifreli `XSRF-TOKEN`'ı) okundu; eşleme yöntemi kendi SDK'sının
  gönderdiği başlıklardan.
- Her cevaba kaynak URL'si ya da komut; bulunamayan "bulunamadı",
  ölçülmeyen "ölçülmedi". Sonuç karar değil, **seçenek + Ç-no**
  (`rakip-arastirmasi.md`: 5 araç × 4 soru, Ç24–Ç28).

## Hukuki/ticari olgular

Vergi, Apple kuralları, ödeme sağlayıcısı uygunluğu gibi olgular birincil
kaynaktan okunur; ikincil kaynaksa "ikincil" diye işaretlenir ve
doğrulanacak açık uç açılır (Ç12: mali müşavir + hukukçu; Ç29: Apple
kural sayfası doğrudan okunmadı).
