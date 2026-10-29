# Geri düğmesi üst çubukta (kullanıcı denemesi, Ç61 devamı)

2026-10-27 · kullanıcı: "Geri düğmesi çalışıyor ama arayüz kötü".

## Ölçüm (önce, tarayıcı)
"← Geri" ilerleme çubuğunun ÜSTÜNDE ayrı satırda, düz yazı; ilk adımda
olmadığı için ikinci adımda çubuk ve içerik ~50 px aşağı kayıyordu.

## Karar
- Geri ve ilerleme tek satır (`.ust-cubuk`, ızgara `44px 1fr`): solda
  yuvarlak ok simgesi (SVG, `aria-label="Geri"`), sağda çubuk. Geri sütunu
  ilk adımda boş ama yer tutar → sayfa kaymaz.
- Düğme 44×44 (dokunma alanı), üzerine gelince hafif zemin.
- **Reddedilen:** yazılı "← Geri"yi korumak (yer kaplıyor, çubukla
  hizasız) · ilk adımda gizli (`visibility: hidden`) düğme (erişilebilirlik
  ağacında boş düğme riski; ızgara sütunu aynı işi görür).

## Test
`Huni.test.tsx` Geri testi: ilk adımda `.ust-cubuk > progress`, ikincide
`.ust-cubuk > button.geri + progress`; düğme adı "Geri".
`bilesenler.test.tsx`: `button.geri` yükseklik VE genişlik ≥ 44 px.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | üst çubuk sarmalayıcısı yok | 🔴 Geri testi |
| K2 | erişilebilir ad "Dön" | 🔴 Geri testi |
| K3 | düğme genişliği 24 px | 🔴 bileşen CSS testi |

## Gerçek (tarayıcı, yerel huni)
Karşılama: çubuk sağ sütunda, solda boşluk. İkinci adım: solda ‹, çubuk
aynı yerde (kayma yok). Geri tıklaması önceki adıma döner.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0 (ilk koşu bileşen testindeki eski
`min-height` beklentisiyle kırmızıydı; test yeni kurala göre güncellendi).
