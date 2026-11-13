# L14 — Huni silme / arşivleme (Ç41)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
Panelde huni kaldırma yoktu (Ç41); deneme hunileri (`b1deneme`, Ç53 (5))
listede kalıyordu. Şema: `huni_surumleri → huniler` ve
`huni_ziyaretleri → huni_surumleri (app, huni, surum)` yabancı anahtarlı —
ziyareti olan sürüm silinemez (rapor ona bağlı).

## Karar
- `Huniler::kaldir`: kuralları her durumda kalkar; ziyaret yoksa sürümler ve
  huni silinir (`silindi`, ad yeniden kullanılabilir); varsa `arsiv_at`
  (`arsivlendi`). Varsayılan kaldırılamaz ("önce başka bir huniyi varsayılan
  yap").
- Arşivdeki huni: listede yok, `taslak()` null (düzenlenmez, kural almaz,
  kopya kaynağı olmaz), `?huni=` ve kurallarla seçilmez, varsayılan
  yapılamaz. Raporlar ziyaretleri saymaya devam eder.
- Göç `Version20261026130000`: `arsiv_at` + **kısıt**
  `NOT (varsayilan AND arsiv_at IS NOT NULL)` — uygulama kontrolü kısıtı
  görünmez kılar, ayrı testle ölçülür (tuzak §4).
- Panel: varsayılan olmayan satırda ziyaret varsa "Arşivle", yoksa "Sil".
- **Reddedilen:** arşivden geri alma (istenmedi; gerekirse ayrı karar, Ç66)
  · ziyaretleri de silmek (rapor verisi) · kuralı olan huniyi kaldırmayı
  reddetmek (kullanıcı "kurallardan çıkar" dedi).

`kaldir`'da bir kusur testle bulundu: `fetchOne('SELECT varsayilan …')`
varsayılan olmayan huni için `false` döndürüyor, "satır yok" ile aynı →
her kaldırma "yok" diyordu. `fetchAssociative`.

## Test
`HuniTest` +4: ziyaretsiz silinir (sürüm, kural yok; ad yeniden açılır);
ziyaretli arşivlenir (sürüm kalır, kural yok, `?huni=` ve kural varsayılana
düşer, listede yok, taslak yok, kural eklenemez, varsayılan yapılamaz, ikinci
kaldırma "yok ya da arşivde"); varsayılan kaldırılamaz; kısıt doğrudan.
`PanelHuniTest` +1: düğme etiketleri, CSRF, sil + arşivle, liste.
Kısıt testi göçten önce **sahte yeşildi** (sütun yoktu → yine hata) — kırma
K1 ile ölçüldü.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | test DB'sinde kısıt kaldırıldı | 🔴 kısıt testi (sonra geri kondu) |
| K2 | ziyaret yok sayılır, hep silinir | 🔴 2 test (FK hatası) |
| K3 | arşivdeki seçilebilir | 🔴 arşiv testi |
| K4 | kurallar kalır | 🔴 2 test |
| K5 | liste arşivi gösterir | 🔴 2 test |
| K6 | düğme hep "Sil" | 🔴 panel testi |

## Gerçek
Dev ve test DB'sinde göç + kısıt var. Panelden tıklama kullanıcıda (şifre);
`b1deneme` (ziyaretsizse "Sil") ile denenebilir.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 227 çekirdek testi / 1.461 doğrulama.
