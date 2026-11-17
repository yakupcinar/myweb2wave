# L5 — Ulaşılamayan adım uyarısı + yeniden adlandırmada hedef taşıma (Ç55 (1)(2))

2026-10-26 · karar `pre-setup.md` A13 (uyarı; yayın engellenmez).

## Ölçüm (önce)
- Ulaşılamayan adım (hiçbir yolun götürmediği) için ne düzenleyicide ne
  çekirdekte uyarı vardı (`huni-dallanma-h5.md`, Ç55 (1)).
- Bir adımın kimliği değişince ona işaret eden `hedef`ler eski adda
  kalıyordu; kayıt `Tanim.php` hedef doğrulamasında reddediliyordu (Ç55 (2)).
- Oynatıcının kuralı okundu (`Huni.tsx` `ileri`): hedef varsa ona, yoksa
  sıradakine; çoklu/metin soru ve yorum sıradakine; paywall son.

## Karar
- `core/editor/src/akis.ts` (saf):
  - `ulasilamayanlar(tanim)` — oynatıcıyla aynı kural, 0'dan gezinme.
  - `hedefleriYenidenAdlandir(once, sonra)` — aynı Puck bloğunun
    (`props.id`) kimliği değiştiyse ve eski ad artık hiçbir blokta yoksa,
    tanıtım ve tek seçim seçeneği hedeflerini yeni ada taşır; değişiklik
    yoksa `null` (gereksiz `setData` olmasın).
- `main.tsx` `AkisBekcisi` (üst çubukta): Puck deposundan veriyi okur,
  taşıma gerekiyorsa `setData`; ulaşılamayan adım varsa kırmızı not
  "Hiçbir yoldan ulaşılmayan adım: …" (`.puck-not-uyari`).
- **Reddedilen:** yayında reddetmek (kullanıcı uyarıyı seçti) · eski adı
  başka blok taşıyorsa da taşımak (kullanıcının kasıtlı hedefini bozar).

## Test
`akis.test.ts` 5 test (beklenenler elle çizilmiş akıştan, tuzak §3); editör
39/39; tip denetimi + derleme yeşil.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | seçenek hedefi yok sayılır | 🔴 dal testi |
| K2 | çoklu/metin de seçenek yolundan (metin → takılır) | 🔴 sıradakine testi |
| K3 | eski ad başka blokta olsa da taşınır | 🔴 dokunmaz testi |
| K4 | seçenek hedefleri taşınmaz | 🔴 taşıma testi |
| K5 | tanıtım hedefi taşınmaz | 🔴 taşıma testi |
| K6 | değişiklik yokken de yeni veri döner | 🔴 dokunmaz testi |

## Ölçülmeyen
`AkisBekcisi`'nin Puck'a bağlanması (render, `setData`, uyarı satırı) —
düzenleyicide DOM test ortamı yok (Ç53 (2)) ve tarayıcıda panel şifresi
kullanıcıda. Kullanıcı bakacak: bir adımın kimliğini değiştir → ona giden
hedef açılır listede yeni adla görünür; tanıtım hedefini sona ver → aradaki
adım için kırmızı uyarı.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 207 çekirdek testi.
