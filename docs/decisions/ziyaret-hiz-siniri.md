# L15 — Ziyaret hız sınırı, sekmede ziyaret sürdürme, güvenilir vekil (Ç34 + H6b IP hatası)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
- `/huni/ziyaretler` anahtarsız, sınırsız: sahte istekle ziyaret sayıları
  şişirilebilir; sayfa yenilemesi yeni ziyaret sayılıyordu (Ç34, ölçülmüştü).
- **Yan bulgu:** `trusted_proxies` hiç yok. Üretimde çekirdek Caddy'nin
  arkasında (`deploy/compose.yaml`: yalnız `web` 80/443 dışa açık, çekirdek iç
  ağda) → `getClientIp()` Caddy kabının iç IP'si. Etkisi: H6b'de Meta/TikTok'a
  giden eşleşme IP'si **vekilin** IP'si olurdu; IP başına hız sınırı herkese
  ortak tek kova olurdu. Test bunu düzeltmeden önce gösterdi: `172.18.0.5`.

## Karar
- `framework.trusted_proxies: private_ranges`, başlıklar `x-forwarded-for`,
  `x-forwarded-proto`. Güvenli çünkü çekirdek yalnız iç ağdan erişilir ve
  Caddy istemcinin `X-Forwarded-For`'unu yok sayıp kendisi yazar.
- `rate_limiter.huni_ziyaret`: kayan pencere, **IP başına dakikada 120**;
  aşan 429 `too_many_requests` (sözleşmede). 30 değil: mobil operatör ortak
  IP'si (CGNAT) arkasındaki gerçek ziyaretçiler takılmasın. Huni 429'da da
  çalışır (ziyaret yazılmaz, hata yutulur — mevcut davranış).
- Huni: ziyaret kimliği `sessionStorage`'da (`huni-ziyaret:app:huni:sürüm`);
  aynı sekmede yenileme aynı ziyareti sürdürür, başka sürüm yeni ziyaret.
  Sekme kapanınca biter (çerez değil, kalıcı değil). Yeniden kullanılan
  ziyarette izin önceden verilmişse izin ucu da çağrılır (ziyaret izinsiz
  açılmış olabilir). Depo engelliyse her yükleme yeni ziyaret.
- **Reddedilen:** `localStorage` (sekmeler/günler arası birleştirme →
  tarayıcı kimliği; H6a yalnız tercih saklar) · CAPTCHA · uygulama anahtarı
  (huni statik, anahtar sayfada görünür).

## Test
Çekirdek `AtifTest` +2: vekil arkasında ödeme oturumuna gerçek istemci IP'si
yazılır; aynı IP'den 120 × 201, 121. 429 (sözleşmeye uyar), başka IP 201.
Hız sınırı deposu (`cache.rate_limiter`) her testte temizlenir. Huni +3:
yenilemede tek ziyaret, başka sürümde ikinci; sekme deposu engelliyse yine
ziyaret; yeniden kullanılan ziyarette önceki izin izin ucuna gider.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `trusted_proxies` yok | 🔴 vekil + hız sınırı (iki IP aynı kova) |
| K2 | sınır uygulanmaz | 🔴 hız sınırı |
| K3 | sınır 121 | 🔴 hız sınırı |
| K4 | sekmeden okunmaz | 🔴 2 test |
| K5 | sekmeye yazılmaz | 🔴 yenileme |
| K6 | yeniden kullanımda izin bildirilmez | 🔴 izin |
| K7 | depo hatası yakalanmaz | 🔴 depo engelli |
| K8 | anahtarda sürüm yok | 🔴 2 test |

## Gerçek istek
Yerel çekirdeğe 122 ardışık ziyaret: **120 × 201, 2 × 429**, veritabanında
120 satır (ölçüm satırları `utm_source='l15-olcum'` ile sonra silindi).
Tarayıcı: aşağıda.

İlk tarayıcı denemesi **ölçülemedi**: 122'lik ölçümden sonra yerel IP (curl
ve tarayıcı aynı) kayan pencerede hâlâ sınırdaydı → tarayıcı da 429 aldı,
ziyaret yok. Pencere boşalınca (yoklama 201): aynı sekmede iki yükleme →
`sessionStorage` `huni-ziyaret:keepchat:genel:5`, veritabanında **1 ziyaret,
1 adım** satırı.

Dağıtım notu: sınır üretimde Caddy'nin yazdığı gerçek istemci IP'sine
göre; yerelde (vekilsiz) bütün tarayıcı + curl tek kova.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 229 çekirdek testi / 1.617 doğrulama.
