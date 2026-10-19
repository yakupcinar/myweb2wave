# Kararlar ve NEDEN'leri

> Her karar: **ne** · **neden** · **reddedilen alternatifler ve sebebi** ·
> tarih. Karar değişirse eskisi silinmez, üstü çizilir ve yenisi
> gerekçesiyle altına yazılır.

## §A — Ürün

**A1 · Tek kiracı: kendi uygulamalarımız için** (2026-10-19, kullanıcı
kararı). Huniler kodda tanımlanır, görsel editör yok. Reddedilen: başka
geliştiricilere SaaS — çok kiracılı, editörlü, aylar süren iş ve web2wave'le
doğrudan rekabet; "önce kendim sonra SaaS" — şimdiden tenant_id taşımak
ölçülmemiş bir ihtiyaca altyapı kurmak.

**A2 · İlk hedef: kullanıcının mevcut iOS uygulaması** (2026-10-19). Hangi
uygulama olduğu ve web'den ödemenin gerekip gerekmediği açık: Ç9.

**A3 · Ödeme: Stripe TEST modu** (2026-10-19). Şirket Türkiye'de; gerçek
ödeme yok. ⚠️ Stripe Türkiye şirketine canlı hesap açmıyor (ikincil
kaynaklar, resmi listeden ölçülmedi) — canlıya geçiş ayrı karar: Ç10.

## §B — Yığın

**B1 · TypeScript: Next.js (App Router) + Postgres + Stripe Node SDK,
Docker ile kendi sunucumuzda** (2026-10-19). Kullanıcı ölçütü: deneyim
değil, işe en uygun dil.

**Neden:**
- Ürünün yüzü **etkileşimli huni** (adımlar, ilerleme, istemci durumu,
  geçişler) ve ileride **görsel editör** — ikisi de React ekosisteminin
  işi. TypeScript'te huni, API, webhook ve editör **tek dilde**.
- Huni sayfası hızı: sunucu bileşenleri sayfayı sunucuda üretir, yalnız
  etkileşimli parça istemciye JS taşır (web2wave'in "sunucuda HTML + az JS"
  yolunun modern karşılığı).
- Ödeme: Stripe, Paddle ve iyzico'nun **resmi** Node SDK'ları var →
  Ç10'un üç yolu da açık.
- Doğrulama: `tsc --noEmit` + Vitest + **Playwright** (huniyi gerçek
  tarayıcıda baştan sona yürütür — blok adım 6'nın otomatik hâli).

**Reddedilen:**
- **Go** — sunucu tarafı için iyi, ama huni arayüzü ve editör için ikinci
  bir dil (JS/TS) yine gerekir; abonelik yaşam döngüsü elle yazılır.
- **Laravel** — web2wave'in yığını; Cashier (Stripe + Paddle) gerçek bir
  artı. Ama etkileşimli huni ve editör için Inertia + React'e gidilir → iki
  dil; PHP dağıtımı ayrı bir düzen.
- **Astro** — statik sayfada en hızlısı, ama huni durumu + webhook + editör
  için uygulama çatısı değil.
- **SvelteKit** — teknik olarak uygun; editör kütüphanelerinde (sürükle-bırak,
  form kurucular) ekosistem React'ten küçük.
- **Vercel'e dağıtım** — sunucusuz kilitlenme ve maliyet belirsizliği;
  `output: standalone` + Docker ile kendi sunucumuz.

⚠️ **Risk:** Next.js'in sürümden sürüme değişen önbellek/yönlendirme
davranışı — sessiz yanlış sonuç adayı; ilk blokta ölçülecek (risk tablosu).

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

**C4 · web2wave'e kara kutu gözlemle bakılır, tersine mühendislikle değil**
(2026-10-19). Serbest: herkese açık sayfa/başlık/belge okumak, SDK'nın
**davranışını** okumak, deneme hesabıyla huniyi müşteri gibi yürümek.
Yasak: bot korumasını aşmak, panel/API'ye yetkisiz istek, derlenmiş kodu
açmak, lisanssız SDK'dan ya da "güvenlik değerlendirmesinden yeniden
yapım" türü klon depolardan kod almak (kural 12; kaynağı belirsiz).

## Risk tablosu

| Risk | Olasılık | Etki | Ne zaman ölçülür |
|---|---|---|---|
| R1 · Next.js önbelleği huni sayfasında bayat/yanlış kişiye ait içerik gösterir | orta | yüksek | ilk huni bloğu: iki farklı kullanıcıyla aynı sayfa |
