---
name: kontrol
description: >-
  Projenin tam doğrulaması — biçim + statik analiz + test süiti + derleme,
  çekirdek (Symfony) ve huni (Astro) için. Tek komut bin/kontrol. sinayici
  ajanı bunu çağırır; bir blok bitince ve commit öncesi koşulur.
---

# /kontrol — tam doğrulama

```bash
bin/kontrol > /tmp/kontrol.log 2>&1; echo EXIT=$?
```

⚠️ `bin/kontrol | tail` YAZMA — çıkış kodu `tail`'in olur (tuzak §1).
Betik zaten her adımın kodunu borusuz alıyor; sen de betiğinkini öyle al.

Her şey Docker'da koşar (makinede PHP yok, Node sürümü önemsiz — pre-setup
B3). Ön koşul: Docker Desktop açık.

## Adımlar (betiğin içi, sırayla)

| # | Adım | Komut (konteynerde) | Ölçüt |
|---|---|---|---|
| 0 | ortam | `df -h .` · `docker compose up -d --wait db stripe-mock` | disk %90+ ise sahte kırmızı riski (tuzak §14) |
| 1 | core-kur | `composer install` — temiz ortamda `vendor/` yok (CI EXIT=127 dersi) | |
| 1 | core-bicim | `php-cs-fixer check --diff` | EXIT≠0 = biçimsiz (kırma K5: EXIT=8) |
| 1 | funnel-npm / funnel-bicim | `npm ci` · `prettier --check .` | |
| 1 | sozlesme-kur / sozlesme-guncel | `npm ci` · `openapi-typescript` → `/tmp` + `cmp` depodaki `funnel/src/sozlesme/api.ts` | farklıysa: sözleşme değişmiş, `contract npm run uret` unutulmuş |
| 2 | core-composer | `composer validate --strict` | |
| 2 | core-stan | `phpstan analyse` (level **max**) | |
| 2 | funnel-tip | `astro check` | |
| 3 | core-testdb / core-goc / core-test | `doctrine:database:create --env=test` · `doctrine:migrations:migrate --env=test` · `phpunit` | **passed/skipped sayısı** ayrıca basılır (tuzak §8, §20) |
| 3 | funnel-derle | `astro build` | |

Kırmızı adımın günlüğünün **son** 40 satırı ekrana basılır (hata sonda; ilk 40 satır CI'da Composer hatasını gizledi); tamamı betiğin
yazdığı `log:` dizininde.

## Rapor

Adım başına: ad · EXIT · süre. Test sayısını ayrı yaz. Bir adım altyapı
yüzünden koşamadıysa (Docker kapalı, port dolu) **"koşmadı"** de, "geçti" deme.
