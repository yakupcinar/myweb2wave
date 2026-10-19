---
name: kontrol
description: >-
  Projenin tam doğrulaması — biçim + statik analiz + test süiti. sinayici
  ajanı bunu çağırır; bir blok bitince ve commit öncesi koşulur. Sıra
  önemli: ucuz kontrol önce, pahalı süit sonra.
---

# /kontrol — tam doğrulama

⚠️ **HENÜZ DOLDURULMADI — yığın seçilince yazılır.** Bu dosya boşken
hiçbir komut uydurma; "kontrol tanımlı değil, koşmadım" de.

Her adımda çıkış kodu **borusuz** alınır (CLAUDE.md tuzak §1):
`komut > /tmp/kontrol-N.log 2>&1; echo EXIT=$?`

## 0. Ortamı say (tuzak §14)
- `df -h` — disk dolu mu
- Veri servisleri ayakta mı: _komut_

## 1. Biçim
_komut_ — ⚠️ ölçüt çıkış kodu mu, çıktının boş olması mı? (tuzak §5)

## 2. Statik analiz
_komut_

## 3. Test süiti
_komut_ — ayrı test veritabanı, paralel koşum ayarı

## Rapor
Her adım için: komut · EXIT · süre · ilk kırmızı satır.
Atlanan test sayısını ayrı yaz (tuzak §8).
