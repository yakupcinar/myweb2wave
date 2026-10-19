# Kararlar ve NEDEN'leri

> Her karar: **ne** · **neden** · **reddedilen alternatifler ve sebebi** ·
> tarih. Karar değişirse eskisi silinmez, üstü çizilir ve yenisi
> gerekçesiyle altına yazılır.

## §A — Ürün

## §B — Yığın

## §C — Araç ortamı

**C1 · Ritüel skill'leri projede yaşar, kullanıcı düzeyi `ritual` eklentisi
bu projede kapalı** (2026-10-19). Neden: proje kopyaları depoyla
sürümlenir, başka makineye/bulut oturumuna gider ve projeye göre
budanabilir (KeepChat artıkları ancak böyle temizlendi). Reddedilen:
(a) proje kopyalarını silip eklentiye dayanmak — eklenti yalnız bu Mac'te,
artıkları temizlemek onu kullanan diğer projeleri de değiştirir;
(b) ikisini birden tutmak — skill'ler iki kez listelenir, hook iki kez koşar.

**C2 · Graphify şimdilik YOK** (2026-10-19). Neden: depoda kod yok; graf
keşif maliyetini düşürür, keşfedilecek bir şey yok. Bayat graf, sessizce
yanlış cevap üretme riski taşır (tuzak §12'nin akrabası). Tetikleyici ve
ölçüm planı: Ç8.
**Ölçüldü (2026-10-19, KeepChat, n=1 × 3 soru):** Graphify'lı kol her soruda
grafı sorguladı ama yine kaynağa gitti; toplam token +%4/+%4/+%15, cevaplar
iki kolda da doğru → **eklenmez**. Yeniden açılma koşulu: ajanın keşifte
onlarca dosyayı tek tek okuduğu ölçülürse (`ai-ortami-incelemesi.md` §7).

**C3 · Yığın seçilene kadar kural 11'in kapısı: elle ölçüm** (2026-10-19,
kullanıcı kararı). `/kontrol` tanımsızken yalnız ayar/belge değişiklikleri,
ölçümü kayıtlıysa commit + push edilir (ör. `ai-ortami-incelemesi.md`
§5: oturum init satırı, hook vakaları, artık grep'i). Kod girdiği anda
istisna biter; kapı yeniden `/kontrol` olur.

## Risk tablosu

| Risk | Olasılık | Etki | Ne zaman ölçülür |
|---|---|---|---|
