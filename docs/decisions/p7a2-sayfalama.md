# P7a2 — Büyüyen tablolar sayfalı (Aboneler + Rapor'un üç listesi)

**Tarih:** 2026-11-10 · **İstek (kullanıcı):** "Tablolar sayı arttıkça sonsuza kadar aşağı gidiyor;
web2wave nasıl ayarlamış?" Kararlar: **numaralı sayfalama** ve **büyüyen tüm tablolar**.

## Ölçüm (önce)

- web2wave (tarayıcıda, yalnız okundu): User Visits ve Subscriptions'ta sayfalama sunucuda ve numaralı;
  listeler zorunlu tarih aralığıyla açılıyor
  ([web2wave-platform-yapisi.md](web2wave-platform-yapisi.md) "Uzun listeler").
- Bizde dört liste en çok 200 satırı tek seferde çiziyordu: Aboneler, Rapor → Ziyaretler, Kaynak
  kırılımı, Serbest yanıtlar. Yerel veride 90 günde 116 ziyaret vardı.

## Ne yapıldı

- **Ortak bileşen** [ui/Sayfalama.tsx](../../core/panel/src/uygulama/ui/Sayfalama.tsx): altta
  "1–25 / 116 ziyaret · ‹ Önceki · Sonraki ›". Tek sayfaya sığan listede yalnız sayı yazar
  ("7 abonelik").
- **Sunucu:** sayfa başına 25 (`PanelController::SAYFA_BOYUTU`); yanıtta `sayfa`, `boyut`, `toplam`.
  `sayfa` parametresi 1'den başlar ve 1–10000 arasına çekilir.
- **Aboneler:**
  - Sıralama da sunucuya taşındı (`sirala` = email / durum / kaynak / sonOdeme, `yon` = artan / azalan).
  - Sayfa, sıralama ve süzgeçler adreste durur.
  - Süzgeç ya da sıra değişince ilk sayfaya dönülür.
  - `Kullanicilar::liste` ve `say` aynı `GOVDE` SQL'ini kullanır; iki kopya ayrışamaz.
- **Rapor:**
  - Üç liste kendi uçlarından gelir: `/panel/api/rapor/{ziyaretler|kaynaklar|serbestYanitlar}`.
  - `PanelRaporu`'ndan `satirlar`, `utm`, `metinCevaplari` ve `azami` alanları kalktı.
  - Her tablonun sayfası kendi; sayfa değişince raporun geri kalanı yeniden hesaplanmaz.
- **AI araçları (MCP):** `liste()` ve `utm()` varsayılan değerleriyle eskisi gibi en çok 200 satır döner;
  davranışları değişmedi.

## Neden böyle

- **Numaralı sayfa, sunucuda.** Reddedilenler (kullanıcıya önizlemeyle soruldu):
  - Tarih aralığı + sayfalama: eski bir aboneyi bulmak için tarihi değiştirmek gerekir.
  - "Daha fazla göster": çok tıklanınca sayfa yine uzar.
  - Tablo içinde kaydırma: 200 sınırı ve iç içe kaydırma kalır.
- **Sıralama sunucuda.** Sayfalı listede istemci yalnız bir sayfayı görür; istemcide sıralamak yalnız o
  25 satırı sıralardı. Boş değer küçük sayılır: artanda başta, azalanda sonda. Durum, paneldeki Türkçe
  sırayla sıralanır (Aktif, Denemede, İptal edildi, Ödeme sorunlu, Bitti, Bekliyor); alfabetik değil.
- **OFFSET, imleç değil.** Panel listeleri yönetici gözü için ve küçük; sayfa numarası ve "/ toplam"
  gerekiyor. Her sırada benzersiz bir son anahtar var (`stripe_subscription_id`, `z.id`,
  `(ziyaret_id, soru)`, grup anahtarının tamamı); sayfa sınırında satır kaybolmaz ya da tekrarlanmaz.
  Arada yeni kayıt gelirse bir satır kayabilir; olay akışı (§53) değil, kabul edildi.
- **Rapor listeleri ayrı uçta.** Tek uçta üç ayrı sayfa parametresi, her sayfa değişiminde 12 sorgunun
  hepsini yeniden koştururdu.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| S1 | Aboneler OFFSET hep 0 | testSayfalama düştü |
| S2 | Toplam süzgeci yok sayar | testSayfalama |
| S3 | Boş değer artanda sonda | testSunucudaSiralama |
| S4 | İptali planlı aktif "Aktif" sırasında | testSunucudaSiralama |
| S5 | Kaynak toplamı grup değil ziyaret sayar | testRaporListeleriSayfali |
| S6 | Ziyaret OFFSET 0 | testRaporListeleriSayfali |
| S7 | Serbest yanıt OFFSET 0 | testRaporListeleriSayfali |
| S8 | "Sonraki" son sayfada açık | Rapor.test 1/8 |
| S9 | Tek sayfada da aralık yazar | Rapor.test 1/8 |
| S10 | Süzgeç değişince sayfa korunur | Kullanicilar.test 2/9 |
| S11 | Sıralama uca gitmez | Kullanicilar.test 1/9 |
| S12 | Liste raporun gününü almaz | Rapor.test 1/8 |

12/12 kırmızı, hepsi assertion (sözdizimi ya da SQL hatası değil).

İki ölçülmeyen nokta:
- Sayfa parametresinin alt sınırını bozmak (`max(1,…)` kaldırmak) OFFSET'i eksiye düşürür ve 500 verir;
  bu sahte kırmızı olurdu (§8), denenmedi.
- Serbest yanıtlardaki eşitlik bozucu sıra `(ziyaret_id, soru)` aynı anlı yanıtlar gerektirir;
  ölçülmedi.

## Ölçüm

- `bin/kontrol` YEŞİL: çekirdek 740 test (+3). Panelde Kullanicilar 9 ve Rapor 8 test.
- Tarayıcı (yerel veri):
  - Rapor 90 gün: "1–25 / 116 ziyaret". "Sonraki" ile ilk satır 2026-11-09 07:18'den 2026-10-25 22:47'ye
    geçti ve "26–50 / 116" yazdı.
  - Kaynaklar "13 kaynak", serbest yanıtlar "0 yanıt".
  - Aboneler `?sirala=durum`: önce dört "Aktif", sonra üç "Bekliyor"; başlıkta `aria-sort=ascending`.
  - `/panel/api/kullanicilar?sayfa=2` → `[2, 25, 7, 0]`.
- Yolda §58 tekrar görüldü: `Kullanicilar::liste`'nin satır bağlantısı #L39 → #L72 kaydı. `bin/kontrol`
  yakaladı, `--duzelt` ile düzeltildi.
