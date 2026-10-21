# Blok: H2 — huni oynatıcısı React'e (B4) — 2026-10-21

## Ne yapıldı
Huni sayfası artık Astro'da **tek React adacığı** (`funnel/src/oynatici/Huni.tsx`,
`client:only="react"`). Adımları panel önizlemesiyle aynı bileşenler
çiziyor (`huni-bilesenleri`, H1). `HuniKabugu.astro`'daki ~250 satırlık düz
TS oynatıcısı silindi; kabukta yalnız HTML, ortak CSS ve tanımı **erken
başlatan satır içi betik** kaldı. H0'daki 15 davranışın (D1–D15) her biri
testle ölçülüyor (huniye Vitest + Testing Library + happy-dom). Ortak
bileşenlere isteğe bağlı etkileşim alanları eklendi (`onDugme`, `onSecim`,
`kapali`, `hata`); Puck bunları geçmiyor.

Ekranda: temalı huni (`?huni=fitness`) adım adım yürüdü, geri/ileri tuşu
adımlar arasında, odak başlıkta, cevaplar çekirdeğe yazıldı, "Abone ol"
Stripe **test** ödeme sayfasına yönlendi ve oturum ziyarete bağlandı.

## Neden böyle

| Karar | Elenen | Neden |
|---|---|---|
| Tanım isteği **satır içi betikte**, fonksiyonun kaynağı derlemede gömülür (`tanimIstegi.toString()`) | (a) isteği oynatıcı JS'inde başlatmak (b) küçük bir giriş modülünde başlatıp React'i dinamik yüklemek (c) elle yazılmış JS dizgesi | H0 bulgusu: React zinciri uzatır. (a) H0'ın sorununu büyütür (b) yine bir JS turu bekler (c) testsiz ikinci kopya olurdu. Kaynaktan gömmek tek kopya + tip denetimi + test (yalıtık kapsamda yeniden kurulup çağrılıyor) |
| `client:only="react"` (`@astrojs/react` 7.0.1) | Elle `createRoot` yapan Astro betiği | Astro'nun tsconfig'i `jsx: preserve`; React JSX'i için entegrasyon gerekiyor. İçerik çalışma anında geldiği için sunucuda çizilecek bir şey yok |
| Durum `useState` (adım, ödeme) | `useReducer` (PLAN önerisi), XState/Zustand | Bugün iki bağımsız alan var, geçiş mantığı tek satır (`ileri`). Reducer bunun üstüne soyutlama olurdu (6a); dallanma H5'te |
| Ziyaret `useState` başlatıcısında (bir kez) | `useMemo` / render içinde | React `useMemo`'nun bir kez çalışacağını garanti etmiyor. Render içinde çağırmak her çizimde yeni ziyaret açar (kırma P14 ölçtü) |
| Bütün adımlar DOM'da, gösterilmeyen `hidden` | Yalnız mevcut adımı çizmek | Bugünkü davranış: gizli adımların görselleri baştan iner, geçişte görsel beklenmez |
| Testte ağ, `vi.hoisted` ile **global fetch** sahtesi | `cekirdek`'i taklit etmek (`vi.mock`) | Gerçek istek yolu (openapi-fetch, gövde, sorgu) da ölçülüyor. openapi-fetch `fetch`'i istemci kurulurken aldığı için sahte, içe aktarmalardan önce kurulmalı |
| Bütçe adımı: huninin **bütün** JS'i gzip ≤ 90 KB (`funnel-butce`) | Yalnız anket sayfasının dosyaları | Dosya adları özetli; sayfaya göre ayıklamak kırılgan. Başarı sayfası 0,6 KB, toplam üst sınır güvenli tarafta |
| Huni tarafında kendi `dedupe` satırımız YOK | H1'deki gibi `dedupe` | `@astrojs/react` zaten ekliyor; ölçüldü: satır silinince derleme aynı (P24). Bekçi duruyor ve tutuyor (P24b) |
| Vitest'te `dedupe` YOK | Eklemek | Silinince 22/22 yine yeşil (P27). Ortak bileşenler hook kullanmıyor; ikinci `jsx-runtime` zararsız. Hook gelirse React "invalid hook call" ile sesli çöker |

## Testler — neyi ölçüyor
`funnel/src/oynatici/Huni.test.tsx` (21) + `erken-istek.test.ts` (1):

| D | Test |
|---|---|
| D1 | `<b>`, `<img onerror>` metin olarak görünür, DOM'da öğe yok (+ `html-yok` adımı) |
| D2 | kapak `src` = `${PUBLIC_CORE_URL}/medya/<id>.webp` |
| D3 | önizleme: taslak ucu (`huni`, `anahtar`; seçim parametreleri GİTMEZ), şerit, hiç POST yok, ödeme kapalı |
| D4 | 404 / ağ hatası → "Sayfa yüklenemedi"; önizlemede "süresi dolmuş" |
| D5 | yalnız 5 parametre, ≤ 200; erken istek varsa oynatıcı tanımı yeniden istemez; satır içi kaynak yalıtık kapsamda çalışır |
| D6–D8 | sekme başlığı; tema değişkenleri (`#16a34a` → yazı siyah, bağımsız hesap); tema yoksa kökte değişken yok; çubuk max/value |
| D9–D12 | ziyaret bir kez (huni, sürüm, utm); bildirim sırası 0 → 1 → cevap → 2; `replaceState`/`pushState`, `popstate` geri; odak ilk adımda yok, sonra başlıkta; ziyaret 500 → akış ve ödeme sürer |
| D13–D14 | gövde `{app, plan, ziyaret}`, düğme hemen kapanır, yönlenme; 502+`payment_provider_unavailable` / 502 başka / 422 / ağ → üç metin, düğme açılır |
| D15 | "Yükleniyor…" tanım gelene kadar |

## Kırma denemeleri (yedek → boz → uygulandı mı → hedefli test → geri → `cmp`)

| # | Deneme | Sonuç |
|---|---|---|
| P1 | seçim listesinden `huni` | 🔴 D5 |
| P2 | `slice(0,200)` → 250 | 🔴 D5 |
| P3 | önizlemede de ziyaret açılır | 🔴 D3 |
| P4 | önizlemede ödeme açık | 🔴 D3 |
| P5 | D4 metinleri yer değiştirdi | 🔴 3 test |
| P6 / P6b | `document.title` yazılmıyor | 🔴 (P6b, test düzeltmesinden sonra: 2 test) |
| P7 / P7b | tema `setProperty` yok | 🔴 D7 |
| P8 | çubuk `value={mevcut}` | 🔴 D8 |
| P9 | cevap kimliği gönderilmiyor | 🔴 D9 |
| P10 / P10b | `pushState` yok | 🔴 D10 |
| P10c | ilk `replaceState` yok | 🔴 D10 |
| P11 | `popstate` dinleyicisi yok | 🔴 D10 |
| P12 / P12b | odak yok | 🔴 D11 |
| P13 / P13b | ilk adımda da odak | 🔴 D11 |
| P14 | ziyaret render içinde (her çizimde) | 🔴 2 test |
| P15 | ödeme sürerken düğme açık | 🔴 D13 (+ yan kırmızı, aşağıda) |
| P16 | ödemeye ziyaret gitmiyor | 🔴 D13 |
| P17 | 502 koşulu `&&` → `\|\|` | 🔴 D14 |
| P18 | ağ hatası metni | 🔴 D14 |
| P19 / P19b | yükleniyor metni yok | 🔴 D15 |
| P20 | satır içi kaynak tanımsız ada dayanıyor | ⚠️ **sahte** — her şey çöktü, yalıtım testi geçti → P20b |
| P20b | kaynak **modül sabitine** dayanıyor (modülde çalışır, yalıtıkta değil) | 🔴 yalnız yalıtım testi |
| P21 | oynatıcı erken isteği yok sayar | 🔴 D5 |
| P22 | `onSecim(i+1)` (yanlış seçenek) | 🔴 2 test |
| P23 | tema yokken varsayılan değişkenler yazılır | 🔴 D7 olumsuz test |
| P24 | huni `astro.config` `dedupe` silindi | ⚪ **tutmadı** — `@astrojs/react` kendisi ekliyor → satır kaldırıldı |
| P24b | bileşen `./node_modules/react` içe aktarır | 🔴 derleme: "ikinci kopya pakete giriyor" |
| P25 | bütçe 70.000 B | ⚠️ ilk koşu **KOŞULMADI** (komut iç içe tırnakta bozuldu) → doğrudan: 🔴 çıkış 1 |
| P26 | oynatıcıda ham HTML özniteliği | 🔴 `html-yok` |
| P27 | Vitest `dedupe` silindi | ⚪ tutmadı → satır kaldırıldı (yukarıda) |

**Testteki yarış (tuzak §33):** P15 ve P19'da ilgisiz D7 / D10 testleri de
kırmızıydı, sonra yeşil süit bir kez D10'da düştü. Sebep: başlık, tema ve
`replaceState` `useEffect`'te; `findByText` metni gördüğünde effect'in
koşmuş olması garanti değil. Olumlu iddia aralıklı kırmızı oluyordu; olumsuz
iddia ("tema yoksa değişken yok", "ilk adımda odak yok") effect'ten önce
okununca **yalancı yeşil** olurdu. Düzeltme: effect'e bağlı iddialar
`waitFor` içinde; olumsuz iddiadan önce aynı effect'in yan etkisi (başlık /
ilk bildirim) beklenir. Sonra 3 koşu 22/22 (~1 sn) ve P6b/P7b/P10b/P10c/
P12b/P13b/P23 tekrarlandı. P15'teki yan kırmızı, kasıtlı düşen ödeme
testinin yarım kalan isteğinin sahte `location.assign` geri alındıktan
sonra gerçek yönlenmeyi tetiklemesinden (yalnız deneme sırasında).

## Ölçüm
- `bin/kontrol` **EXIT=0, 213 sn** (macOS, Docker, disk %99, 2,8 GB boş).
  Çekirdek 169, huni 22, bileşen 18, düzenleyici 5 test.
- **Boyut:** huni JS gzip toplamı **73.229 B** (H0: 4.822 B; bütçe 92.160).
  Dosyalar (gzip -9): `client` (React DOM + renderer) 65.690 · `react`
  3.042 · `Huni` 2.683 · `cekirdek` 2.584 · başarı sayfası 565.
  `index.html` 7.540 B (satır içi ada betiği + CSS + erken istek).
- **Zincir** (Chrome, yayın derlemesi `python3 -m http.server` ile `:4321`,
  çekirdek ısınmış; sekme **arka planda**, `visibilityState=hidden`):
  - Önbellekli: tanım 18 ms'de başlıyor, JS dosyaları 19 ms'de — **paralel**.
  - Önbelleği atlayan iki koşu: tanım 174 / 284 ms, `Huni.js` + `client.js`
    187 / 303 ms (paralel); `react.js` + `cekirdek.js` **724 / 1189 ms**
    (ikinci sıralı JS turu); ilk adım bildirimi 864 / 2196 ms.
  - Arka plan sekmesinde süreler iki koşu arasında 2,5 kat oynadı. Duvar
    saati süresi bu araçla **ölçülemiyor**; FCP/LCP kaydı yok (H0 ile aynı
    kısıt, Ç50).
  - Sıralı ağ adımları: H0 = HTML → JS → `cekirdek` → **tanım** (4). H2 =
    HTML → {tanım ∥ `Huni` ∥ `client`} → {`react`, `cekirdek`}. Tanım artık
    JS'i beklemiyor; ama ada bağımlılıkları ikinci bir JS turu oluşturuyor
    (modulepreload yok) → Ç52.
- Gerçek istek (geliştirme sunucusu): ziyaret `20826cfe…` → adımlar 0/1/2,
  cevap `kiminle=aile`; `cs_test_a1TQ…` ödeme oturumu aynı ziyarete bağlı.
  Geçersiz önizleme → "süresi dolmuş" metni.

## Ortam notları
- `huni-dev` yeniden açıldı, artık iki ek bağlama var:
  `$PWD/huni-bilesenleri:/huni-bilesenleri` (yazılabilir) ve
  `myweb2wave_funnel-node-modules:/huni-bilesenleri/node_modules` (Ç51 (1)).
  Zincir ölçümü için durduruldu, sonra yeniden başlatıldı. Kontrolün
  `funnel-npm` adımı aynı birimi yeniden kurduğu için sonrasında
  `docker restart huni-dev` gerekti.
- `reference.php` yine yeniden yazıldı, HEAD hâline döndürüldü (Ç51 (2)).
- Yerelde test süitini 3 kez art arda koşturdum (yarış düzeltmesinden sonra,
  her biri ~1 sn). Kural 9'un "döngüyle süit koşma" sınırına yakın; yük hafif.
