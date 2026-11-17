# V2 — hazır temalar (2026-11-03)

**İstek:** V serisi (kullanıcı 2026-11-03). Rakipler: FunnelFox global tema +
öğe bazında ezme; Heyflow 600+ stil değişkeni + tasarım içe aktarma
(`rakip-gorsellik-arastirmasi.md`).

## Ölçüm (önce)

AI isteminde 5 sabit örnek tema (`Tema::AI_ORNEKLERI`); panelde hazır tema
yok — yönetici 15 alanı tek tek seçiyor.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | `Tema::HAZIR_TEMALAR`: 12 adlı tema (anahtar, ad, uygun ürünler, tema) — AI örneklerinin yerine | AI ve panel için ayrı listeler | Tek kaynak (kural 7); panel API'den alır, AI istemi aynı listeyi adlarıyla görür |
| 2 | Tanımda "hazır tema" alanı YOK — seçim temanın değerlerini kopyalar | `tema: "gece"` başvurusu | Hazır tema sonradan değişse yayındaki huniler sessizce değişirdi; tanım kendi başına yeter |
| 3 | Panelde kökte "Hazır tema" seçici (temanın kendi renkleriyle düğmeler) + kökün `resolveData`'sı alanları açılıştaki eşlemeyle (`kokTemasi`, `tanimdanPuck`'tan çıkarıldı) doldurur, seçimi boşaltır | ayrı eşleme | İkinci eşleme sessizce ayrışırdı (ör. kart/zemin) |
| 4 | Her tema yayın denetiminden geçer (kontrast dahil) ve gerçek ekranda görüldü | — | Geçerli ≠ güzel |
| 5 | Tohum renkten palet (material-color-utilities, Apache-2.0) **ertelendi** → V2b | bu blokta | Hazır temalar AI'ın renk uydurmasını zaten bitiriyor; tohum palet marka rengi olan yönetici için, ayrı bağımlılık |

## Ölçümler

- 12 tema × 2 ekran (soru, bilgi) gerçek Chrome'da çekildi (geçici
  `tema-deneme` hunisi, sonra dev DB'den silindi); temas sayfası incelendi:
  hepsi okunur ve birbirinden ayırt edilir.
- **Bulgu:** aynı içerik bir temada uyarısız, ötekinde uyarılı (yazı tipi ve
  boşluk satır kırılmasını değiştiriyor: gece 0 uyarı, nane bilgi 2) →
  yönergeye "tema değişince bütün ekranlara yeniden bak".
- Panel (gerçek oturum, kullanıcının açık oturumu; şifre girilmedi):
  "Enerji" tıklandı → alanlar `#f97316/#111111/#f5f5f5`, geçiş `#1c1917`,
  özel tema evet, büyük başlık, orta, dolu düğme; önizleme CSS değişkenleri
  güncellendi (tuzak §42: Puck'ta ölçüldü, birim testinde değil yalnız).
- `bin/kontrol` YEŞİL: 460 çekirdek testi / 3709 iddia (yönerge testi dahil).

## Kırma denemeleri (8/8 kırmızı; W7 ilk denemede KOŞULMADI, W8 testi buldu)

| # | Bozulan | Düşen |
|---|---|---|
| W1 | bir temanın kontrastı | TemaTest |
| W2 | istemde temaların listesi | TemaTest |
| W3 | API'de tema sırası/eşleşmesi | PanelHuniTest |
| W4 | sayfa → düzenleyici aktarımı | HuniDuzenle.test |
| W5 | seçim alanının boşalması | yapilandirma.test |
| W6 | özel tema açılması | yapilandirma.test |
| W7 | kart eşlemesi (`kokTemasi`) | yapilandirma.test — ilk yama biçimlendirici yüzünden tutmadı (KOŞULMADI, §8), düzeltildi |
| W8 | yönergede tema kuralı | ilk denemede **yeşil**: istem testi aynı sabitle karşılaştırıyordu (§3) → `TasarimYonergesiTest` elle yazılmış parçalarla, sonra kırmızı |
