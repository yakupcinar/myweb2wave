<?php

declare(strict_types=1);

namespace App\Uygulama;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * BR2: uygulamanın kod tabanında çalışan bir yapay zekâya verilecek talimat —
 * panelde "Yapay zekâ için kopyala", BR3'te MCP aracı aynı metni döndürür (tek
 * kaynak). Uçlar, sıra, test ve App Store kuralları uygulamaya özel yazılır;
 * gizli değer YOK, anahtar yer tutucudur. Eksik adımlar BaglantiDurumu'ndan
 * ölçülür — yapay zekâ önce eksik olanı yapar. Kaynak `docs/uygulama-entegrasyonu.md`;
 * biri değişirse öteki de (kural 7).
 */
final readonly class EntegrasyonRehberi
{
    public function __construct(
        private BaglantiDurumu $baglanti,
        private UygulamaAyarlari $ayarlar,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $adres,
    ) {
    }

    public function metin(string $app): string
    {
        $d = $this->baglanti->durum($app);
        $ayar = $this->ayarlar->oku($app);
        $ad = '' !== $ayar['ad'] ? $ayar['ad'] : $app;
        $sema = '' !== $ayar['sema'] ? $ayar['sema'] : '<şema — panel › Ayarlar\'da henüz boş, önce orada girilmeli>';
        $taban = rtrim($this->adres, '/');
        $eksik = array_filter([
            $d['anahtar']['tamam'] ? null : 'Uygulama anahtarı çekirdekte boş — panel sahibi sunucuya UYGULAMA_ANAHTARI_'.strtoupper($app).' girmeli (sen değil).',
            $d['istek']['tamam'] ? null : 'Uygulamanın sunucusundan geçerli anahtarla hiç istek gelmedi — §1 yapılandırma.',
            $d['baglama']['tamam'] ? null : 'Henüz kimse bağlanmadı — §2 "Aboneliğimi bağla".',
            $d['girisBaglantisi']['tamam'] ? null : 'Ödeme sonrası giriş bağlantısı hiç kullanılmadı — §3.',
            !$d['revenuecat']['acik'] || $d['revenuecat']['tamam'] ? null : 'RevenueCat aktarımı açık ama hiç gönderilmedi — bağlama olunca gider; uygulamada §4.',
        ]);
        $eksikMetni = [] === $eksik ? '- Yok; bütün zorunlu adımlar çalışan sistemde ölçüldü.' : '- '.implode("\n- ", $eksik);
        $rc = $d['revenuecat']['acik']
            ? "Hak kaynağı RevenueCat: bağlama ya da giriş 200 dönünce `kisi`'yi sakla ve RevenueCat SDK'sında `Purchases.logIn(kisi)` çağır; hakkı SDK'dan sor. Çekirdek web aboneliğini RevenueCat'e kendisi aktarır. RevenueCat ürün kimliği Stripe ürün kimliğiyle aynı olmalı. `/hak` ucu yedek."
            : 'Bu uygulamada RevenueCat aktarımı kapalı: hakkı §2 adım 3\'teki `/hak` ucundan sor.';

        return <<<MD
            # {$ad} → MyWeb2Wave bağlantısı (uygulama: `{$app}`)

            Sen bu uygulamanın kod tabanında çalışıyorsun. Görev: kullanıcının web'de (MyWeb2Wave hunisi, Stripe)
            aldığı aboneliği uygulamada tanımak. Sıra aşağıdaki gibi; önce "Şu an eksik" listesindekileri yap.

            ## Şu an eksik (çalışan sistemden ölçüldü)

            {$eksikMetni}

            ## Değişmez kurallar

            - Çekirdeğe YALNIZ uygulamanın sunucusu konuşur. Uygulama anahtarı istemcide (iOS ikilisi, tarayıcı,
              depo) ASLA bulunmaz; ortam değişkeninden okunur.
            - Uygulama kendi girişini korur; çekirdeğe yalnız "bu kullanıcıyı bu e-postaya bağla" ve "hakkı var mı"
              sorulur.
            - Bağlamayı oturum açmış kullanıcı için yap. `kullanici` = uygulamanın KALICI kullanıcı kimliği, dizge
              (sayısal kimlik ondalık: "42"); değişebilen ad değil.
            - Bağlantının kaynağı çekirdek; uygulama bağlantıyı saklamak zorunda değil (göç gerekmez).

            ## 1 · Yapılandırma (uygulamanın sunucusu)

            | Değişken | Değer |
            |---|---|
            | Çekirdek adresi | `{$taban}` — boşsa özellik kapalı kalmalı |
            | Uygulama anahtarı | `<UYGULAMA_ANAHTARI>` — panel sahibinden alınır, sırdır, depoya girmez |
            | Uygulama adı | `{$app}` |

            Bütün isteklerde başlık `Authorization: Bearer <UYGULAMA_ANAHTARI>`; yanlış ya da eksik → 401.

            ## 2 · "Aboneliğimi bağla" (e-posta kodu)

            1. İstemci → sunucu: e-posta. Sunucu → `POST {$taban}/apps/{$app}/baglama/kod` `{"email"}` → 202 (her zaman;
               e-postanın varlığı sızmaz) · 429 (10 dakikada 3'ten fazla).
            2. Kullanıcı e-postadaki 6 haneli kodu girer (10 dakika geçerli, 5 deneme). Sunucu →
               `POST {$taban}/apps/{$app}/baglama/dogrula` `{"email", "kod", "kullanici"}` → 200 `{"kisi"}` · 422
               `invalid_code` / `expired_code` / `too_many_attempts`.
            3. Gerekince: `GET {$taban}/apps/{$app}/kullanicilar/{kullanici}/hak` → 200 `{"aktif", "durum"}` · 404
               `unknown_user` (bağlanmamış). `aktif` = active · trialing · past_due; duraklatılmış abonelik
               `aktif: false, durum: "paused"`.

            Önbellek: başarılı hak cevabı 5 dakika; çekirdeğe ulaşılamazsa son bilinen durum en çok 24 saat, sonra
            "bilinmiyor" — kullanıcıyı kilitleme. Kod/doğrula uçları önbelleğe alınmaz. Kendi tarafında da oran sınırı koy.

            ## 3 · Ödeme sonrası giriş bağlantısı

            Web'de ödeyen kişi uygulamayı `{$sema}://giris?jeton=<jeton>` ile açar.

            1. iOS: Info.plist'e şema (`CFBundleURLTypes`); `onOpenURL`'de `giris` ana bilgisayarını ve `jeton`
               sorgusunu oku. Jeton gizlidir: günlüğe ve analitiğe yazma.
            2. İstemci → sunucu: jeton. Sunucu → `POST {$taban}/apps/{$app}/giris/kullan` `{"jeton", "kullanici"}` → 200
               `{"kisi"}` · 422 `invalid_token` (bilinmiyor/kullanılmış) / `expired_token` (24 saat) / `invalid_request`.
               Giriş yapmamış kişi için önce uygulamanın kendi anonim hesabını aç.
            3. 422'de §2'yi öner. Jeton tek kullanımlık; ikinci cihaz §2 ile bağlanır.

            ## 4 · Hak

            {$rc}

            ## 5 · App Store kuralları (iOS)

            - Uygulamanın içinden web'de satın almaya yönlendirme YOK (bağlantı, düğme, "web'den al" metni) — ABD
              vitrini dışında yasak. Bağlama ekranı yalnız "zaten abone misin? e-postanla bağla" der.
            - Web aboneliğiyle kilit açmak, aynı ürün uygulama içi satın almada da satılıyorsa serbest (3.1.3(b));
              yoksa ret riski — ilk adımda yalnız durumu göster.

            ## 6 · Test ve bitti ölçüsü

            - Her uç için: başarılı yanıt, 401 (yanlış anahtar), hata kodları (422/404/429) ayrı test.
            - Bitti = panel › Uygulama › "Uygulama bağlantısı" listesinde adımlar çalışan sistemden tamamlanır
              (elle işaretlenmez). Yerel denemede kod e-postaları çekirdeğin posta kutusuna düşer; gerçek anahtar
              ve gerçek e-posta panel sahibinindir.
            MD;
    }
}
