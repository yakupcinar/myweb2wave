<?php

declare(strict_types=1);

namespace App\Tests\Olay;

use App\Atif\Ziyaretler;
use App\Billing\SubscriptionSync;
use App\Huni\Huniler;
use App\Olay\Olaylar;
use Doctrine\DBAL\Connection;
use Stripe\Event;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * T4: olay deposu — ziyaretin zaman çizelgesi ve uygulamanın olay akışı.
 * Zamanlar elle sabitlenir, beklenen sıra elle yazılır (tuzak §3).
 */
final class OlaylarTest extends KernelTestCase
{
    private Connection $db;
    private Ziyaretler $ziyaretler;
    private Olaylar $olaylar;
    private SubscriptionSync $sync;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE fatura_odemeleri, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, subscriptions, processed_webhook_events, reklam_donusumleri, revenuecat_gidenler, odeme_faturalari CASCADE');
        $ziyaretler = self::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $ziyaretler);
        $this->ziyaretler = $ziyaretler;
        $this->olaylar = new Olaylar($db);
        $sync = self::getContainer()->get(SubscriptionSync::class);
        self::assertInstanceOf(SubscriptionSync::class, $sync);
        $this->sync = $sync;

        // Ziyaret huni/sürümü yalnız yayınlanmış çiftle yazılır.
        $huniler = self::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'kiminle', 'tur' => 'ekran', 'baslik' => 'Kiminle?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'aile', 'metin' => 'A'], ['id' => 'is', 'metin' => 'B']]]]],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));
    }

    /** Ziyaret → izin → iki adım + cevaplar → ödeme → abonelik; gerçek yazma yollarından (M5a). */
    private function tamZiyaret(string $app = 'keepchat'): string
    {
        $z = $this->ziyaretler->kaydet($app, array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, true);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is', 'aile']));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'Gizli metin'));
        self::assertTrue($this->ziyaretler->ilerle($z, 2, 'paywall', null));
        self::assertTrue($this->ziyaretler->oturumBagla($z, $app, 'cs_'.$z, null, null));
        $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'eur',
            'metadata' => ['app' => $app]]);
        $this->fatura('in_'.$z, $z, 999, 'subscription_create', $app);
        $this->abonelik($z, 'active', 1_800_000_000, $app);

        return $z;
    }

    /** @param array<string, mixed> $nesne */
    private function stripe(string $tur, array $nesne, int $zaman = 1_800_000_000): void
    {
        $this->sync->apply(Event::constructFrom(['id' => 'evt_'.bin2hex(random_bytes(6)), 'object' => 'event', 'type' => $tur, 'created' => $zaman, 'data' => ['object' => $nesne]]));
    }

    /** M5b: gerçek faturanın biçimi (API 2026-09-30): abonelik ve uygulama parent.subscription_details'te. */
    private function fatura(string $id, string $z, int $tutar, string $neden, string $app = 'keepchat', int $odendi = 1_800_000_050): void
    {
        $this->stripe('invoice.paid', ['id' => $id, 'object' => 'invoice', 'status' => 'paid', 'amount_paid' => $tutar, 'currency' => 'eur',
            'billing_reason' => $neden, 'metadata' => [], 'status_transitions' => ['paid_at' => $odendi],
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_'.$z, 'metadata' => ['app' => $app]]]]);
    }

    private function abonelik(string $z, string $durum, int $zaman, string $app = 'keepchat'): void
    {
        $this->stripe('customer.subscription.updated', ['id' => 'sub_'.$z, 'object' => 'subscription', 'customer' => 'cus_1', 'status' => $durum, 'metadata' => ['app' => $app]], $zaman);
    }

    /** @return list<string> */
    private function adlar(string $z): array
    {
        // JSONB anahtar sırası korunmaz (tuzak §28) — anahtarlar sıralı.
        return array_map(static function (array $o): string {
            $veri = $o['veri'];
            ksort($veri);

            return $o['ad'].([] === $veri ? '' : ' '.json_encode($veri, \JSON_UNESCAPED_UNICODE));
        }, $this->olaylar->ziyaretin($z));
    }

    public function testZiyaretinZamanCizelgesi(): void
    {
        $z = $this->tamZiyaret();

        // Sıra yazılış sırası (id) — neden önce, sonuç sonra.
        self::assertSame([
            'huni.ziyaret {"huni":"genel","surum":1}',
            'izin.verildi',
            'adim.goruldu {"adim":"kiminle","sira":0}',
            'cevap.verildi {"secenekler":["aile","is"],"soru":"kiminle"}',
            'adim.goruldu {"adim":"not","sira":1}',
            // Serbest yanıt metni olaya girmez (Ç90) — yalnız hangi soru.
            'cevap.verildi {"soru":"not"}',
            'adim.goruldu {"adim":"paywall","sira":2}',
            'odeme.baslatildi',
            'odeme.tamamlandi',
            'odeme.fatura {"neden":"subscription_create","paraBirimi":"eur","tutar":999}',
            'abonelik.durum {"durum":"active"}',
        ], $this->adlar($z));
        $zamanlar = array_column($this->olaylar->ziyaretin($z), 'zaman');
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/', $zamanlar[0]);
        $sirali = $zamanlar;
        sort($sirali);
        self::assertSame($sirali, $zamanlar);
        self::assertStringNotContainsString('Gizli metin', (string) json_encode($this->olaylar->ziyaretin($z)));
    }

    /**
     * Ç92: geçmiş korunur — cevap değişimi, izin geri alma ve yeniden verme
     * ayrı olay; durum değişmeyen tekrar (aynı cevap, aynı adım, zaten izinli)
     * olay değil.
     */
    public function testGecmisKorunurTekrarOlayDegil(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['aile']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['is']));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', []));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'a'));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'a'));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'not', null, 'b'));
        self::assertTrue($this->ziyaretler->izinVer($z, []));
        self::assertTrue($this->ziyaretler->izinVer($z, []));
        self::assertTrue($this->ziyaretler->izinGeriAl($z));
        self::assertTrue($this->ziyaretler->izinGeriAl($z));
        self::assertTrue($this->ziyaretler->izinVer($z, []));

        self::assertSame([
            'huni.ziyaret',
            'adim.goruldu {"adim":"kiminle","sira":0}',
            'cevap.verildi {"secenekler":["aile"],"soru":"kiminle"}',
            'cevap.verildi {"secenekler":["is"],"soru":"kiminle"}',
            'adim.goruldu {"adim":"not","sira":1}',
            'cevap.verildi {"soru":"not"}',
            'cevap.verildi {"soru":"not"}',
            'izin.verildi',
            'izin.reddedildi',
            'izin.verildi',
        ], $this->adlar($z));
    }

    /**
     * Ç92: abonelik durum geçmişi (active → past_due → canceled); aynı durum
     * tekrar ya da sıra dışı gelen eski olay (tuzak §13) olay değil.
     */
    public function testAbonelikDurumGecmisi(): void
    {
        $z = $this->tamZiyaret();
        $this->abonelik($z, 'active', 1_800_000_100);
        $this->abonelik($z, 'past_due', 1_800_000_200);
        $this->abonelik($z, 'active', 1_800_000_150);
        $this->abonelik($z, 'canceled', 1_800_000_300);

        self::assertSame(['active', 'past_due', 'canceled'], array_column(array_column(array_filter($this->olaylar->ziyaretin($z), static fn (array $o): bool => 'abonelik.durum' === $o['ad']), 'veri'), 'durum'));
    }

    /** Abonelik olayı ödeme oturumundan önce gelirse durum bağlanınca çizelgeye girer (bir kez). */
    public function testAbonelikOturumdanOnceGelirse(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, true);
        self::assertTrue($this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_'.$z, null, null));
        $this->abonelik($z, 'trialing', 1_800_000_000);
        // Aynı oturumun tamamlanması iki ayrı olay kimliğiyle gelse de tek olay.
        foreach ([1, 2] as $_) {
            $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
                'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 0, 'currency' => 'eur',
                'metadata' => ['app' => 'keepchat']]);
        }

        self::assertSame(['huni.ziyaret', 'izin.verildi', 'odeme.baslatildi', 'odeme.tamamlandi', 'abonelik.durum {"durum":"trialing"}'], $this->adlar($z));
    }

    /**
     * M5b (A18): tahsil edilen fatura ziyarete bağlanır. İlk faturanın
     * invoice.paid'i oturumdan önce gelebilir — bağlanınca çizelgeye girer;
     * deneme sonrası yenileme de; aynı fatura iki kez gelse tek; tutar 0
     * (deneme başlangıcı) gelir değil.
     */
    public function testFaturaZiyareteBaglanir(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        self::assertTrue($this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_'.$z, null, null));
        $this->fatura('in_deneme', $z, 0, 'subscription_create');
        $this->fatura('in_ilk', $z, 999, 'subscription_create', 'keepchat', 1_800_000_010);
        self::assertSame(['huni.ziyaret', 'odeme.baslatildi'], $this->adlar($z), 'oturum bağlanmadan olay yok');
        $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'eur',
            'metadata' => ['app' => 'keepchat']]);
        $this->fatura('in_yenileme', $z, 1299, 'subscription_cycle', 'keepchat', 1_802_600_000);
        $this->fatura('in_yenileme', $z, 1299, 'subscription_cycle', 'keepchat', 1_802_600_000);

        self::assertSame([
            'huni.ziyaret',
            'odeme.baslatildi',
            'odeme.tamamlandi',
            'odeme.fatura {"neden":"subscription_create","paraBirimi":"eur","tutar":999}',
            'odeme.fatura {"neden":"subscription_cycle","paraBirimi":"eur","tutar":1299}',
        ], $this->adlar($z));
        self::assertSame(
            [['in_ilk', 'sub_'.$z, 'keepchat', 999, '2027-01-15 08:00:10+00'], ['in_yenileme', 'sub_'.$z, 'keepchat', 1299, '2027-02-14 10:13:20+00']],
            array_map('array_values', $this->db->fetchAllAssociative("SELECT stripe_invoice_id, stripe_subscription_id, app, tutar_kucuk, to_char(odendi_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS+00') FROM odeme_faturalari ORDER BY odendi_at")),
        );
    }

    /**
     * Uygulama faturanın kendi metadata'sından değil aboneliğinkinden (gerçek
     * faturada kendi metadata'sı boş — ölçüldü); aboneliksiz fatura yazılmaz.
     */
    public function testFaturaUygulamasiAbonelikten(): void
    {
        $this->stripe('invoice.paid', ['id' => 'in_ust', 'object' => 'invoice', 'amount_paid' => 999, 'currency' => 'eur', 'metadata' => ['app' => 'keepchat'],
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_x', 'metadata' => []]]]);
        $this->stripe('invoice.paid', ['id' => 'in_tek', 'object' => 'invoice', 'amount_paid' => 999, 'currency' => 'eur', 'metadata' => [],
            'parent' => ['type' => 'quote_details', 'quote_details' => ['quote' => 'qt_1'], 'subscription_details' => null]]);
        $this->stripe('invoice.paid', ['id' => 'in_abonelik', 'object' => 'invoice', 'amount_paid' => 999, 'currency' => 'eur', 'metadata' => [],
            'parent' => ['type' => 'subscription_details', 'subscription_details' => ['subscription' => 'sub_y', 'metadata' => ['app' => 'keepchat']]]]);

        self::assertSame(['in_abonelik'], $this->db->fetchFirstColumn('SELECT stripe_invoice_id FROM odeme_faturalari'));
    }

    /**
     * M6a: iade Stripe'ın birikimli amount_refunded'ı — tekrar olay iki kez
     * düşmez, ikinci kısmi iade farkı kadar olay, tutarı aşamaz. Ödeme bağı
     * (invoice_payment.paid) faturadan önce de gelebilir; bağı olmayan iade yok sayılır.
     */
    public function testIadeBirikimliVeBagdan(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        self::assertTrue($this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_'.$z, null, null));
        $this->stripe('checkout.session.completed', ['id' => 'cs_'.$z, 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_'.$z, 'customer' => 'cus_1', 'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'eur', 'metadata' => ['app' => 'keepchat']]);
        $iade = fn (int $toplam, string $pi = 'pi_1') => $this->stripe('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $pi, 'amount_refunded' => $toplam]);
        $this->stripe('invoice_payment.paid', ['id' => 'inpay_1', 'object' => 'invoice_payment', 'invoice' => 'in_1', 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_1']]);
        $this->fatura('in_1', $z, 999, 'subscription_create');
        $iade(300);
        $iade(300);
        $iade(500);
        $iade(5000);
        $iade(999, 'pi_bilinmeyen');

        self::assertSame(999, $this->db->fetchOne("SELECT iade_kucuk FROM odeme_faturalari WHERE stripe_invoice_id = 'in_1'"));
        self::assertSame([300, 200, 499], array_column(array_column(array_values(array_filter($this->olaylar->ziyaretin($z), static fn (array $o): bool => 'odeme.iade' === $o['ad'])), 'veri'), 'tutar'));
    }

    /** M6a: iptal planı ve dönem sonu abonelik olayından — dönem sonu kalemde (API 2026-09-30). */
    public function testIptalPlaniVeDonemSonu(): void
    {
        $this->stripe('customer.subscription.updated', ['id' => 'sub_d', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active', 'cancel_at_period_end' => true,
            'items' => ['object' => 'list', 'data' => [['id' => 'si_1', 'object' => 'subscription_item', 'current_period_end' => 1_800_000_000]]], 'metadata' => ['app' => 'keepchat']]);

        self::assertSame([true, '2027-01-15 08:00:00'], array_values((array) $this->db->fetchAssociative(
            "SELECT iptal_donem_sonu, to_char(donem_sonu AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') FROM subscriptions WHERE stripe_subscription_id = 'sub_d'",
        )));
    }

    /** M6c: duraklatma abonelik olayının pause_collection'ından; kalkınca temizlenir (status active kalır — ölçüldü). */
    public function testDuraklatmaAbonelikOlayindan(): void
    {
        $sub = ['id' => 'sub_p', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active', 'metadata' => ['app' => 'keepchat']];
        $this->stripe('customer.subscription.updated', $sub + ['pause_collection' => ['behavior' => 'void', 'resumes_at' => 1_800_000_000]]);
        $oku = fn (): array => array_values((array) $this->db->fetchAssociative(
            "SELECT status, duraklatildi, to_char(duraklatma_bitis AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') FROM subscriptions WHERE stripe_subscription_id = 'sub_p'",
        ));
        self::assertSame(['active', true, '2027-01-15 08:00:00'], $oku());

        $this->stripe('customer.subscription.updated', $sub + ['pause_collection' => null]);
        self::assertSame(['active', false, null], $oku());
    }

    /** Yalnız ekleme: geçmiş yeniden yazılamaz (DB kısıtı, uygulamadan bağımsız — tuzak §4). */
    public function testOlayGuncellenemez(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);

        $this->expectExceptionMessage('yalnız ekleme');
        $this->db->executeStatement("UPDATE olaylar SET ad = 'izin.verildi' WHERE ziyaret_id = :z", ['z' => $z]);
    }

    /**
     * Akış imleci bitmemiş işlemin olayını atlamaz: id işlem içinde alınır,
     * görünürlük commit'te. Yarım işlemden (küçük id) sonra başlayıp biten
     * işlemin olayı da beklenir; yarım işlem bitince ikisi de gelir, sıra
     * işlem sırası (önce başlayan önce).
     */
    public function testBitmemisIslemAkistaBeklenir(): void
    {
        $a = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $b = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $ilk = $this->olaylar->akis('keepchat', null, 10);
        self::assertNotNull($ilk);
        self::assertCount(2, $ilk['olaylar']);

        // Ayrı bağlantı = ayrı işlem (aynı test veritabanı).
        $ikinci = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $ikinci->beginTransaction();
        Olaylar::ekle($ikinci, $a, 'izin.verildi');
        Olaylar::ekle($this->db, $b, 'izin.reddedildi');

        $imlec = $ilk['imlec'];
        self::assertSame([], $this->olaylar->akis('keepchat', $imlec, 10)['olaylar'] ?? null, 'yarım işlemin arkası da beklenir');
        $ikinci->commit();
        $ikinci->close();
        self::assertSame(
            [[$a, 'izin.verildi'], [$b, 'izin.reddedildi']],
            array_map(static fn (array $o): array => [$o['ziyaret'], $o['ad']], $this->olaylar->akis('keepchat', $imlec, 10)['olaylar'] ?? []),
        );
    }

    /**
     * İki BİTMİŞ işlem: eski işlem (A) büyük id'yle, yeni işlem (T) küçük id'yle.
     * Sayfa 1 ile okununca sıra işlem sırası olmalı — id sırasıyla okuyan
     * imleç (T'den sonra) A'yı (eski işlem) geride bırakıp atlar.
     */
    public function testIkiBitmisIslemIslemSirasiyla(): void
    {
        $z = $this->ziyaret();
        $a = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $t = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $a->beginTransaction();
        $a->fetchOne('SELECT pg_current_xact_id()');
        $t->beginTransaction();
        $t->fetchOne('SELECT pg_current_xact_id()');
        Olaylar::ekle($t, $z, 'izin.verildi');
        Olaylar::ekle($a, $z, 'izin.reddedildi');
        $t->commit();
        $a->commit();
        $a->close();
        $t->close();

        $gorulen = [];
        $imlec = null;
        do {
            $sonuc = $this->olaylar->akis('keepchat', $imlec, 1);
            self::assertNotNull($sonuc);
            array_push($gorulen, ...array_column($sonuc['olaylar'], 'ad'));
            $imlec = $sonuc['imlec'];
            self::assertLessThan(10, \count($gorulen));
        } while ($sonuc['devam']);

        self::assertSame(['huni.ziyaret', 'izin.reddedildi', 'izin.verildi'], $gorulen);
    }

    private function ziyaret(): string
    {
        return $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
    }

    /**
     * Tuzak §53'ün ikinci kolu: işlem kimliği DAHA ESKİ olan işlem (A) olayını
     * yarım işlemden (T) SONRA ekleyip biter — A'nın id'si büyük, işlemi eski,
     * bekçiyi geçer. id imleci A'yı verip T'nin küçük id'li olayını kalıcı
     * atlar; imleç (işlem, id) sırasında olmalı.
     */
    public function testEskiIslemBuyukIdIleYarimIsleminOlayiniAtlatmaz(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $bas = $this->olaylar->akis('keepchat', null, 100);
        self::assertNotNull($bas);
        $once = \count($bas['olaylar']);
        $a = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $t = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $a->beginTransaction();
        $a->fetchOne('SELECT pg_current_xact_id()');
        $t->beginTransaction();
        $t->fetchOne('SELECT pg_current_xact_id()');
        Olaylar::ekle($t, $z, 'izin.verildi');
        Olaylar::ekle($a, $z, 'izin.reddedildi');
        $a->commit();

        $gorulen = [];
        $imlec = null;
        $okut = function () use (&$imlec, &$gorulen): void {
            do {
                $sonuc = $this->olaylar->akis('keepchat', $imlec, 1);
                self::assertNotNull($sonuc);
                foreach ($sonuc['olaylar'] as $o) {
                    $gorulen[] = $o['ad'];
                }
                $imlec = $sonuc['imlec'];
            } while ($sonuc['devam']);
        };
        $okut();
        $t->commit();
        $okut();
        $a->close();
        $t->close();

        // Her olay tam bir kez; A (eski işlem) önce okunur, T bitince gelir.
        self::assertSame(['huni.ziyaret', 'izin.reddedildi', 'izin.verildi'], \array_slice($gorulen, $once - 1), implode(', ', $gorulen));
    }

    public function testIzniReddedenZiyaret(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, false);

        self::assertSame(['huni.ziyaret', 'izin.reddedildi'], array_column($this->olaylar->ziyaretin($z), 'ad'));
    }

    /** Akış imleçle sayfalanır: her olay tam bir kez, sırayla; başka uygulama yok. */
    public function testAkisImlecleSayfalanir(): void
    {
        $a = $this->tamZiyaret('keepchat');
        $b = $this->tamZiyaret('keepchat');
        $this->tamZiyaret('baska');

        $gorulen = [];
        $imlec = null;
        $sayfa = 0;
        do {
            $sonuc = $this->olaylar->akis('keepchat', $imlec, 3);
            self::assertNotNull($sonuc);
            ['olaylar' => $olaylar, 'imlec' => $imlec, 'devam' => $devam] = $sonuc;
            self::assertLessThanOrEqual(3, \count($olaylar));
            foreach ($olaylar as $o) {
                $gorulen[] = ($o['ziyaret'] === $a ? 'a ' : ($o['ziyaret'] === $b ? 'b ' : '? ')).$o['ad'];
            }
            self::assertLessThan(10, ++$sayfa);
        } while ($devam);

        $ziyaret = ['huni.ziyaret', 'izin.verildi', 'adim.goruldu', 'cevap.verildi', 'adim.goruldu', 'cevap.verildi', 'adim.goruldu', 'odeme.baslatildi', 'odeme.tamamlandi', 'odeme.fatura', 'abonelik.durum'];
        self::assertSame([
            ...array_map(static fn (string $ad): string => "a {$ad}", $ziyaret),
            ...array_map(static fn (string $ad): string => "b {$ad}", $ziyaret),
        ], $gorulen);
    }

    public function testAyniAndakiOlaylarAyrilir(): void
    {
        // Aynı işlemdeki olayların zamanı aynı (now()): ziyaret + izin tek işlemde.
        // İmleç yine her birini bir kez vermeli.
        $z = $this->tamZiyaret();
        self::assertSame(1, $this->db->fetchOne("SELECT count(DISTINCT zaman) FROM olaylar WHERE ziyaret_id = :z AND ad IN ('huni.ziyaret', 'izin.verildi')", ['z' => $z]));

        $hepsi = [];
        $imlec = null;
        do {
            $sonuc = $this->olaylar->akis('keepchat', $imlec, 1);
            self::assertNotNull($sonuc);
            ['olaylar' => $olaylar, 'imlec' => $imlec, 'devam' => $devam] = $sonuc;
            array_push($hepsi, ...$olaylar);
            // İmleç ilerlemezse sonsuz döngü yerine düşsün.
            self::assertLessThan(20, \count($hepsi));
        } while ($devam);

        self::assertCount(2, array_filter($hepsi, static fn (array $o): bool => 'cevap.verildi' === $o['ad']));
        self::assertCount(11, $hepsi);
    }

    public function testBozukImlecVeKimlik(): void
    {
        self::assertSame([], $this->olaylar->ziyaretin('x'));
        self::assertNull($this->olaylar->akis('keepchat', 'bozuk', 10));
    }

    /** Görünümün ürettiği her ad sözlükte (yeni olay türü sözlüğe yazılmadan eklenemez). */
    public function testHerOlaySozlukte(): void
    {
        $z = $this->tamZiyaret();
        // M6a: iade yolculuğun parçası değil — sözlük için ayrıca (ödeme bağı + charge.refunded).
        $this->stripe('invoice_payment.paid', ['id' => 'inpay_1', 'object' => 'invoice_payment', 'invoice' => 'in_'.$z, 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_'.$z]]);
        $this->stripe('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_'.$z, 'amount_refunded' => 999]);
        $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, false);

        $adlar = array_map(static fn (mixed $a): string => \is_string($a) ? $a : '', $this->db->fetchFirstColumn('SELECT DISTINCT ad FROM ziyaret_olaylari ORDER BY ad'));
        self::assertSame(array_keys(Olaylar::SOZLUK), array_values(array_intersect(array_keys(Olaylar::SOZLUK), $adlar)));
        self::assertSame([], array_values(array_diff($adlar, array_keys(Olaylar::SOZLUK))));
    }

    public function testKonsol(): void
    {
        $z = $this->tamZiyaret();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:olaylar'));

        self::assertSame(Command::SUCCESS, $komut->execute(['ziyaret' => $z]));
        $cikti = json_decode($komut->getDisplay(), true, 16, \JSON_THROW_ON_ERROR);
        self::assertIsArray($cikti);
        self::assertCount(11, $cikti);

        self::assertSame(Command::SUCCESS, $komut->execute(['--sozluk' => true]));
        self::assertStringContainsString('odeme.tamamlandi', $komut->getDisplay());
        self::assertSame(Command::INVALID, $komut->execute(['ziyaret' => 'x']));
    }
}
