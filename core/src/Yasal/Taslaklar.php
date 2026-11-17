<?php

declare(strict_types=1);

namespace App\Yasal;

/**
 * M9b1 (A23): yasal metinlerin BAŞLANGIÇ TASLAĞI — hukuki görüş değildir;
 * panel bunu yayınlamadan önce hukukçuya gösterilmesi uyarısıyla sunar.
 * Yönetici kaydetmeden hiçbir metin yayında değildir.
 *
 * Metinler sistemin ÖLÇÜLMÜŞ davranışını anlatır (2026-11-10): serbest
 * yanıtlar 90 gün (MetinCevaplariSilCommand::SAKLAMA_GUN), izinsiz e-posta
 * aynı süre; eşleştirme için IP / tarayıcı bilgisi ödemede ya da en geç 48 sa
 * içinde silinir; reklam pikselleri ve tıklama kimlikleri yalnız izinle;
 * tarayıcıda izin tercihi (localStorage) ve sekmelik ziyaret kimliği
 * (sessionStorage). Davranış değişirse taslak da değişir.
 *
 * Biçim: "## " başlık, "- " madde, boş satır paragraf; {{alan}} yer tutucu.
 */
final class Taslaklar
{
    public const BELGELER = ['sartlar', 'gizlilik', 'kvkk', 'cerez'];
    public const DILLER = ['tr', 'en'];

    /** belge => dil => başlık */
    public const BASLIKLAR = [
        'sartlar' => ['tr' => 'Kullanım şartları', 'en' => 'Terms of use'],
        'gizlilik' => ['tr' => 'Gizlilik politikası', 'en' => 'Privacy policy'],
        'kvkk' => ['tr' => 'KVKK aydınlatma metni', 'en' => 'Information notice (KVKK)'],
        'cerez' => ['tr' => 'Çerez ve izleme bildirimi', 'en' => 'Cookie and tracking notice'],
    ];

    public static function metin(string $belge, string $dil): string
    {
        return self::METINLER[$belge][$dil] ?? throw new \InvalidArgumentException("bilinmeyen belge: {$belge}/{$dil}");
    }

    private const METINLER = [
        'sartlar' => [
            'tr' => <<<'MD'
Bu şartlar, {{unvan}} ("biz") tarafından sunulan {{uygulama}} aboneliği ve bu web sayfaları için geçerlidir. Satın alarak bu şartları kabul etmiş olursunuz.

## Hizmet
{{uygulama}}, abonelikle kullanılan bir mobil uygulamadır. Web'de satın alınan abonelik, ödemede kullandığınız e-posta adresiyle uygulamaya bağlanır.

## Abonelik, deneme ve yenileme
- Abonelik, seçtiğiniz dönem sonunda siz iptal edene kadar aynı tutarla kendiliğinden yenilenir.
- Ücretsiz deneme sunuluyorsa kart bilgisi baştan alınır; deneme bitmeden iptal etmezseniz ilk ücret deneme sonunda çekilir.
- Fiyat ve dönem, ödeme sayfasında gösterildiği gibidir. Fiyat sonradan değişirse mevcut aboneliğiniz eski fiyattan sürer.

## İptal
Aboneliğinizi istediğiniz zaman, uygulamadaki abonelik yönetimi bölümünden ya da e-postanızla istediğiniz yönetim bağlantısından iptal edebilirsiniz. İptal, içinde bulunulan dönemin sonunda geçerli olur; o tarihe kadar erişiminiz sürer.

## İade
İade talepleri için {{eposta}} adresine yazın. Bulunduğunuz ülkenin tüketici mevzuatından doğan haklarınız saklıdır.

## Ödeme
Ödemeler Stripe aracılığıyla alınır. Kart bilgileriniz bizim sistemlerimizde saklanmaz.

## Sorumluluk
Hizmet "olduğu gibi" sunulur; makul özeni gösteririz ancak kesintisiz çalışmayı garanti etmeyiz.

## Değişiklikler ve iletişim
Bu şartları güncelleyebiliriz; geçerli sürüm her zaman bu sayfadadır.

{{unvan}} · {{adres}} · {{kayitNo}} · {{eposta}}
MD,
            'en' => <<<'MD'
These terms apply to the {{uygulama}} subscription and these web pages provided by {{unvan}} ("we"). By purchasing, you agree to these terms.

## Service
{{uygulama}} is a mobile app used with a subscription. A subscription bought on the web is linked to the app through the email address you used at checkout.

## Subscription, trial and renewal
- Your subscription renews automatically at the same price at the end of each period until you cancel.
- If a free trial is offered, card details are collected up front; unless you cancel before the trial ends, the first charge is made when it ends.
- Price and period are as shown on the checkout page. If the price changes later, your existing subscription continues at the old price.

## Cancellation
You can cancel at any time from the subscription management section in the app or through the management link you request by email. Cancellation takes effect at the end of the current period; you keep access until then.

## Refunds
For refund requests, write to {{eposta}}. Your rights under the consumer laws of your country are not affected.

## Payment
Payments are processed by Stripe. We do not store your card details.

## Liability
The service is provided "as is"; we take reasonable care but do not guarantee uninterrupted operation.

## Changes and contact
We may update these terms; the current version is always on this page.

{{unvan}} · {{adres}} · {{kayitNo}} · {{eposta}}
MD,
        ],
        'gizlilik' => [
            'tr' => <<<'MD'
Bu politika, {{uygulama}} web sayfalarında ve aboneliğinizde hangi kişisel verileri neden işlediğimizi anlatır. Veri sorumlusu: {{unvan}}, {{adres}}.

## Topladığımız veriler
- **E-posta adresi:** anket sırasında ya da ödemede yazdığınız adres; aboneliği uygulamaya bağlamak ve işlem e-postaları (giriş bağlantısı, doğrulama kodu, abonelik yönetimi) için.
- **Anket cevapları:** size uygun içeriği göstermek ve hizmeti geliştirmek için. Serbest metin yanıtları 90 gün sonra silinir.
- **Ziyaret bilgileri:** geldiğiniz kampanya (UTM parametreleri), gördüğünüz adımlar ve ödeme durumu.
- **IP adresi ve tarayıcı bilgisi:** ödemeyi doğru ziyarete eşlemek için; ödeme tamamlanınca ya da en geç 48 saat içinde silinir.
- **Reklam tıklama kimlikleri** (Meta, TikTok): yalnız izin verirseniz.
- **Ödeme ve abonelik bilgileri:** Stripe'tan gelen abonelik durumu, fatura ve iade kayıtları. Kart bilgileriniz bizde saklanmaz.

## Pazarlama e-postaları
Anket sırasında açıkça izin verdiyseniz, yarım kalan ödemeniz için en fazla iki hatırlatma e-postası gönderebiliriz. Her e-postadaki bağlantıyla abonelikten çıkabilirsiniz. İzin vermediğiniz e-posta adresi 90 gün sonra silinir.

## Hizmet sağlayıcılarımız
- Stripe — ödeme
- Postmark — e-posta gönderimi
- RevenueCat — uygulamadaki abonelik hakkı
- Meta ve TikTok — reklam ölçümü, yalnız izninizle
- Sunucu barındırma hizmeti

Bu sağlayıcıların bir kısmı verileri yurt dışında işleyebilir.

## Haklarınız
Verilerinize erişme, düzeltme, silme, işlemeye itiraz etme ve izninizi geri alma hakkınız vardır. Talepleriniz için {{eposta}} adresine yazın. Türkiye'deki kullanıcılar için ayrıca KVKK aydınlatma metnine bakın.

## İletişim
{{unvan}} · {{adres}} · {{kayitNo}} · {{eposta}}
MD,
            'en' => <<<'MD'
This policy explains what personal data we process on the {{uygulama}} web pages and in your subscription, and why. Data controller: {{unvan}}, {{adres}}.

## Data we collect
- **Email address:** the address you enter in the quiz or at checkout; to link the subscription to the app and for transactional emails (sign-in link, verification code, subscription management).
- **Quiz answers:** to show content that fits you and to improve the service. Free-text answers are deleted after 90 days.
- **Visit information:** the campaign you came from (UTM parameters), the steps you saw and payment status.
- **IP address and browser information:** to match the payment to the right visit; deleted when the payment completes or within 48 hours at the latest.
- **Ad click identifiers** (Meta, TikTok): only if you give consent.
- **Payment and subscription information:** subscription status, invoices and refunds from Stripe. We do not store your card details.

## Marketing emails
If you explicitly agreed in the quiz, we may send at most two reminder emails about an unfinished checkout. You can unsubscribe with the link in every email. An email address without consent is deleted after 90 days.

## Our service providers
- Stripe — payments
- Postmark — email delivery
- RevenueCat — in-app subscription access
- Meta and TikTok — ad measurement, only with your consent
- Server hosting

Some of these providers may process data outside your country.

## Your rights
You have the right to access, correct and delete your data, to object to processing and to withdraw consent. Write to {{eposta}} for any request.

## Contact
{{unvan}} · {{adres}} · {{kayitNo}} · {{eposta}}
MD,
        ],
        'kvkk' => [
            'tr' => <<<'MD'
6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında veri sorumlusu sıfatıyla {{unvan}} ({{adres}}) olarak sizi bilgilendiririz.

## İşlenen veriler ve amaçlar
- **İletişim (e-posta):** aboneliğin kurulması ve uygulamaya bağlanması, işlem e-postaları.
- **Anket cevapları ve ziyaret bilgileri:** hizmetin sunulması ve geliştirilmesi.
- **İşlem güvenliği (IP, tarayıcı bilgisi):** ödemenin doğru ziyarete eşlenmesi; en geç 48 saat içinde silinir.
- **Pazarlama (izinle):** yarım kalan ödeme hatırlatması.
- **Reklam ölçümü (izinle):** reklam tıklama kimlikleri.

## Hukuki sebepler
Sözleşmenin kurulması ve ifası (KVKK m.5/2-c), hukuki yükümlülük (m.5/2-ç), meşru menfaat (m.5/2-f); pazarlama ve reklam ölçümü için açık rıza (m.5/1).

## Aktarım
Veriler hizmetin sunulması için ödeme (Stripe), e-posta (Postmark), abonelik yönetimi (RevenueCat), barındırma ve — izninizle — reklam ölçümü (Meta, TikTok) sağlayıcılarına aktarılır. Bu sağlayıcıların bir kısmı yurt dışındadır; aktarım KVKK m.9'a uygun yapılır.

## Toplama yöntemi
Veriler web sayfalarımızdaki formlar ve ödeme sayfası aracılığıyla elektronik ortamda toplanır.

## Haklarınız (KVKK m.11)
Verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, amacını öğrenme, aktarıldığı kişileri bilme, düzeltilmesini ve silinmesini isteme, itiraz etme ve zararın giderilmesini talep etme haklarınız vardır. Başvurularınızı {{eposta}} adresine iletebilirsiniz.

{{unvan}} · {{adres}} · {{kayitNo}}
MD,
            'en' => <<<'MD'
Under Turkish Personal Data Protection Law No. 6698 ("KVKK"), {{unvan}} ({{adres}}), as data controller, informs you as follows.

## Data processed and purposes
- **Contact (email):** setting up the subscription and linking it to the app, transactional emails.
- **Quiz answers and visit information:** providing and improving the service.
- **Transaction security (IP, browser information):** matching the payment to the right visit; deleted within 48 hours at the latest.
- **Marketing (with consent):** unfinished checkout reminders.
- **Ad measurement (with consent):** ad click identifiers.

## Legal grounds
Formation and performance of a contract (KVKK art. 5/2-c), legal obligation (art. 5/2-ç), legitimate interest (art. 5/2-f); explicit consent for marketing and ad measurement (art. 5/1).

## Transfers
Data is transferred to providers of payments (Stripe), email (Postmark), subscription management (RevenueCat), hosting and — with your consent — ad measurement (Meta, TikTok). Some of these providers are abroad; transfers follow KVKK art. 9.

## Collection method
Data is collected electronically through the forms on our web pages and the checkout page.

## Your rights (KVKK art. 11)
You may learn whether your data is processed, request information, learn the purpose, know the recipients, request correction and deletion, object and claim compensation. Send your requests to {{eposta}}.

{{unvan}} · {{adres}} · {{kayitNo}}
MD,
        ],
        'cerez' => [
            'tr' => <<<'MD'
{{uygulama}} web sayfaları, tarayıcınızda yalnız aşağıdakileri saklar.

## Her zaman (zorunlu)
- **İzin tercihiniz:** izin şeridinde verdiğiniz yanıt, bir sonraki ziyaretinizde yeniden sorulmasın diye (tarayıcı depolaması).
- **Ziyaret kimliği:** sayfa yenilense de anketin aynı ziyarete yazılması için; sekmeyi kapatınca silinir.

## Yalnız izin verirseniz
- **Meta ve TikTok reklam pikselleri:** reklamların etkisini ölçmek için. Bu sağlayıcılar kendi çerezlerini koyar (ör. Meta "_fbp", TikTok "_ttp").
- **Reklam tıklama kimlikleri** (fbclid, ttclid): ödemenin hangi reklamdan geldiğini ölçmek için.

## İzninizi değiştirmek
İzin şeridinden verdiğiniz izni istediğiniz zaman geri alabilirsiniz; geri aldığınızda pikseller yüklenmez ve tıklama kimlikleri saklanmaz.

Sorularınız için: {{eposta}}
MD,
            'en' => <<<'MD'
The {{uygulama}} web pages store only the following in your browser.

## Always (necessary)
- **Your consent choice:** your answer in the consent bar, so you are not asked again on your next visit (browser storage).
- **Visit identifier:** so the quiz is recorded to the same visit when the page reloads; deleted when you close the tab.

## Only if you give consent
- **Meta and TikTok ad pixels:** to measure ad performance. These providers set their own cookies (e.g. Meta "_fbp", TikTok "_ttp").
- **Ad click identifiers** (fbclid, ttclid): to measure which ad a payment came from.

## Changing your consent
You can withdraw consent at any time from the consent bar; once withdrawn, pixels are not loaded and click identifiers are not stored.

Questions: {{eposta}}
MD,
        ],
    ];
}
