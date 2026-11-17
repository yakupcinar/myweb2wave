# A6 — AI değerlendirmesi: brief seti → geçerli tanım oranı, tur, maliyet (2026-11-03)

Plan: A2 sonrası model/efor ayarı için ölçü aracı. **Anahtar yok** (kullanıcı 2026-10-27) → araç ve testleri sahte Claude ile; gerçek ölçüm anahtar gelince (Ç95).

## Karar

- Konsol: `bin/console app:ai-degerlendir [--briefler=…] [--app=keepchat] [--model=…] [--efor=…] [--onek=…]`. Her brief için geçici, boş şablonlu huni açar; **panelin kendi döngüsünü** (`HuniAsistani::calistir`, araçlar, doğrulama-düzeltme, tur sınırı) koşar; sonra huniyi kaldırır (`finally`). Maliyet satırları `ai_istekleri`'nde kalır (gerçek harcama izi).
- Ölçüler: **geçerli** = kaydedildi + sonuç tamam (kayıt çekirdekte doğrulanır, geçersiz tanım kaydedilmez), adım sayısı, tur, maliyet (mikro$), süre; özet: oran, ortalama tur, toplam maliyet, model, efor. Çıktı JSON.
- Model/efor için `HuniAsistani::ayarla(AiAyarlari)` (aynı asistan, başka ayar) — panel davranışı değişmedi.
- Varsayılan brief seti `config/ai/degerlendirme.json` (6 brief: aile güvenliği, iş kullanıcısı, en kısa, kişiselleştirme (T3a), güven/yorum (uydurma sayı yasağı), tema).
- Anahtar boşsa açık mesaj, çıkış 1, **hiçbir istek ve huni yok**.

## Ölçüm

Testler SahteClaude ile (API'nin JSON biçimi): iki brief → 1 geçerli / 1 konuşan, maliyet elle 16.000 + 8.000 mikro$, efor istek gövdesinde, geçici huniler kaldırıldı. Geliştirme sisteminde (anahtar yok): çıkış 1, `ai_istekleri` 0 → 0, geçici huni 0. Gerçek Claude ile koşulmadı.

## Kırma denemeleri (4/4 kırmızı)

E1 geçici huni kaldırılmaz · E2 efor seçeneği yok sayılır · E3 anahtarsız da koşar · E4 kayıt reddi de geçerli sayılır. Testin kendi hatası: boş şablon geçerli tanım değil (boş başlıklar) — örnek tanım düzeltildi; `0.0` JSON'da `0` olarak geliyordu → `JSON_PRESERVE_ZERO_FRACTION`.

## Kontrol

`bin/kontrol` 274 s, EXIT=0, `KONTROL: YEŞİL` — core 421 test / 3.410 doğrulama (önceki koşu PHPStan'da kırmızıydı).
