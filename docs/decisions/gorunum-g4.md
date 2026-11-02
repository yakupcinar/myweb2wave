# G4 — düğme biçimi ve seçenek dizilişi (2026-11-02)

Döngü (kullanıcı 2026-11-02: "benden karar bekleme"). G1 ölçümü: hap düğme (C5) ve çerçeve düğme (C6) küçük etki; ızgara (C8) yalnız soru adımında belirgin (düzen farkı %64).

## Karar
- **`tema.dugmeBicimi`** (`Tema::ON_AYARLAR`, yoksa `dolu`): `dolu` · `cerceve` (saydam, ana renk 2px çizgi ve yazı, gölgesiz) · `hap` (tam yuvarlak). Değişkenler `--dugme-arka/-yazi/-golge/-cizgi/-kose`; yedekler bugünkü dolu düğme (kenar 1px — yeni kural eskisini 2px yapıyordu, fark edildi, değişkene bağlandı, bekçi testi var).
- **Çerçeve okunurluğu (`Tema.php`):** yazı ana renk, düğme saydam → ana renk **düğmenin altındaki yüzeyde** ≥ 4,5:1: kart varsa kart; yoksa geçişte ikinci renk (düğme altta), görselde en kötü siyah/beyaz karışım, yoksa arka plan. Hata öneri verir ("dolu ya da hap seç veya ana rengi koyulaştır").
- **Tam ekran tanıtımda** (G5) düğme her zaman dolu — karartmanın üstünde saydam düğme ana renge göre okunmayabilir.
- **`soru.duzen`**: `liste` (yoksa) · `izgara` (iki sütun, seçenek dikey kart, görsel 4:3 üstte). Yalnız seçmeli soruda; serbest yanıtta hata. Puck'ta "Seçenek dizilişi" (serbest yanıtta gizli).
- **AI:** yönerge `dugmeBicimi` ve `izgara`yı anlatır; "sağlık" örneği hap.
- Reddedilen: çerçeve için ayrı yazı rengi alanı (alan sayısı artar; ana renk tek kaynak) · ızgarayı tema ayarı yapmak (soru başına karar: uzun seçenekler ızgarada sıkışır) · 3 sütun (390 px'te metin sığmaz).

## Ölçüm
- Bağımsız oranlar (Python, §3): #2563eb/#fff 5,17 · #22d3ee/#fff 1,81 · #22d3ee/#0b1120 10,42 · #db2777/#fce7f3 3,91.
- Testler: `TemaTest` (çerçeve: arka plan, kart, geçiş alt rengi; hap/dolu kuralsız; biçim hatası; AI) · `HuniTest` (ızgara tekli/çoklu, serbest yanıtta hata, bilinmeyen değer) · `bilesenler.test.tsx` (işaret yalnız ızgarada, `Adim` aktarır, CSS: ızgara, düğme değişkenleri, kenar 1px, tam ekranda dolu) · `tema.test.ts` (değişkenler) · `donustur.test.ts` (gidiş-dönüş, varsayılan yazılmaz).
- Kırma 13/13 kırmızı: K1 alan denetimi · K2 serbest yanıtta ızgara geçer · K3 çerçeve kontrastı yok · K4 kart yüzeyi yok sayılır · K5 geçiş alt rengi yok sayılır · K6 çerçeve değişkeni yok · K7 CSS düğme arka planı sabit · K8 kenar 2px · K9 tam ekranda dolu değil · K10 listede işaret · K11 `Adim` aktarmaz · K12 Puck soru düzenini okumaz · K13 Puck düğme biçimini yazmaz.
- PHPStan, `$metinYaniti` koşulunu PHPDoc'tan "hep yanlış" saydı (K2 çalıştığını kanıtlıyordu) → denetim yardımcıya taşındı (`soruDuzeni`), K2 yeniden koşuldu: kırmızı.
- Gerçek sistem (KeepChat taslağı yedeklendi → **aynen geri yüklendi**): çerçeve + ızgara ve hap çekildi; `#22d3ee` çerçeve beyaz zeminde reddedildi (1,81:1).
- `bin/kontrol` yeşil (299 çekirdek testi).
- **Ölçülmedi:** Puck'ta alanların görsel kontrolü; ızgarada çok uzun seçenek metni.
