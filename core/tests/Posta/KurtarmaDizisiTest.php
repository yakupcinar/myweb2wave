<?php

declare(strict_types=1);

namespace App\Tests\Posta;

use App\Posta\Bastirma;
use App\Posta\KurtarmaDizisi;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * M8b (A22): terk edilen ödeme kurtarma. Pazarlama e-postası — yalnız izinli
 * adrese, broadcast akışından, Stripe'ın kurtarma adresiyle. Kopya oturum
 * ödenince asıl ziyarete bağlanır. Aynı kişiye 7 günde tek dizi.
 */
final class KurtarmaDizisiTest extends WebTestCase
{
    private const URL = 'https://buy.stripe.com/r/test_kurtarma';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE posta_dizisi, posta_bastirma, subscriptions, processed_webhook_events, huni_ziyaretleri CASCADE');
    }

    // --- webhook ---

    public function testSuresiDolanOturumKurtarmaAdresiniYazar(): void
    {
        $z = $this->ziyaret('a@example.com');
        $this->oturum('cs_1', $z, 0, false);
        $this->oturum('cs_2', $z, 0, false);

        $this->webhook('evt_1', 'checkout.session.expired', ['id' => 'cs_1', 'object' => 'checkout.session', 'after_expiration' => ['recovery' => ['enabled' => true, 'url' => self::URL]]]);
        $this->webhook('evt_2', 'checkout.session.expired', ['id' => 'cs_2', 'object' => 'checkout.session', 'after_expiration' => ['recovery' => ['enabled' => true, 'url' => 'javascript:alert(1)']]]);

        self::assertSame(
            [['cs_1', self::URL], ['cs_2', null]],
            array_map(static fn (array $r): array => [$r['id'], $r['kurtarma_url']], $this->db->fetchAllAssociative(
                'SELECT stripe_checkout_session_id AS id, kurtarma_url FROM odeme_oturumlari ORDER BY 1',
            )),
        );
    }

    public function testKopyaOturumOdenceAsilZiyareteBaglanir(): void
    {
        $z = $this->ziyaret('a@example.com');
        $this->oturum('cs_asil', $z, 2, true);

        $this->webhook('evt_k', 'checkout.session.completed', [
            'id' => 'cs_kopya', 'object' => 'checkout.session', 'mode' => 'subscription', 'recovered_from' => 'cs_asil',
            'subscription' => 'sub_1', 'customer' => 'cus_1', 'customer_details' => ['email' => 'a@example.com'],
            'payment_status' => 'paid', 'amount_total' => 999, 'currency' => 'eur', 'metadata' => ['app' => 'keepchat'],
        ]);

        $kopya = $this->db->fetchAssociative("SELECT ziyaret_id::text AS z, kurtarildigi_oturum, tamamlandi_at IS NOT NULL AS odendi, tutar_kucuk FROM odeme_oturumlari WHERE stripe_checkout_session_id = 'cs_kopya'");
        self::assertSame(['z' => $z, 'kurtarildigi_oturum' => 'cs_asil', 'odendi' => true, 'tutar_kucuk' => 999], $kopya);
        self::assertSame(1, $this->say("SELECT count(*) FROM olaylar WHERE ziyaret_id = '{$z}' AND ad = 'odeme.tamamlandi'"));
        self::assertSame(0, $this->dizi()->gonder(), 'ödenen ziyarete kurtarma gitmez');
    }

    // --- dizi ---

    public function testIzinliAdreseDolunacaIlkAdimYayinAkisindanBirKez(): void
    {
        $izinli = $this->ziyaret('a@example.com');
        $izinsiz = $this->ziyaret('b@example.com', false);
        $this->oturum('cs_1', $izinli, 1, true);
        $this->oturum('cs_2', $izinsiz, 1, true);
        $this->oturum('cs_acik', $this->ziyaret('c@example.com'), 1, false);

        self::assertSame(1, $this->dizi()->gonder());
        self::assertEmailCount(1);
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertSame('a@example.com', $mesaj->getTo()[0]->getAddress());
        self::assertSame('Ödemeniz yarım kaldı', $mesaj->getSubject());
        self::assertSame('KeepChat', $mesaj->getFrom()[0]->getName(), 'M9a: gönderen adı ayarlardan');
        self::assertSame('broadcast', $mesaj->getHeaders()->get('X-PM-Message-Stream')?->getBodyAsString());
        self::assertStringContainsString(self::URL, (string) $mesaj->getTextBody());
        self::assertSame([['cs_1', 1]], $this->kayitlar());

        self::assertSame(0, $this->dizi()->gonder(), 'aynı adım ikinci kez gitmez');
    }

    public function testIkinciAdim24SaatteGecikmisseYalnizSonuncusu(): void
    {
        $this->oturum('cs_1', $this->ziyaret('a@example.com'), 25, true);
        $this->oturum('cs_2', $this->ziyaret('b@example.com'), 25, true);
        $this->db->executeStatement("INSERT INTO posta_dizisi (kaynak, dizi, adim) VALUES ('cs_2', 'kurtarma', 1)");

        self::assertSame(2, $this->dizi()->gonder());
        self::assertSame([['cs_1', 2], ['cs_2', 1], ['cs_2', 2]], $this->kayitlar());
        self::assertSame(0, $this->dizi()->gonder());
    }

    public function testOdeyeneVeAboneOlanaGitmez(): void
    {
        // Aynı ziyaretten başka oturum ödendi.
        $z = $this->ziyaret('a@example.com');
        $this->oturum('cs_1', $z, 2, true);
        $this->db->executeStatement("INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id, tamamlandi_at) VALUES ('cs_odendi', ?, now())", [$z]);
        // Aynı e-posta başka ziyaretten abone (erişim veren durumda); iptal etmiş olan sayılmaz.
        $this->oturum('cs_2', $this->ziyaret('b@example.com'), 2, true);
        $this->abone('sub_b', 'B@Example.com', 'active');
        $this->oturum('cs_3', $this->ziyaret('c@example.com'), 2, true);
        $this->abone('sub_c', 'c@example.com', 'canceled');
        $this->oturum('cs_4', $this->ziyaret('d@example.com'), 2, true);
        $this->abone('sub_d', 'd@example.com', 'active', 'baska-uygulama');

        self::assertSame(2, $this->dizi()->gonder());
        self::assertSame([['cs_3', 1], ['cs_4', 1]], $this->kayitlar());
    }

    public function testAyniKisiyeYediGundeTekDizi(): void
    {
        $this->oturum('cs_eski', $this->ziyaret('a@example.com'), 3, true);
        $this->db->executeStatement("INSERT INTO posta_dizisi (kaynak, dizi, adim, gonderildi_at) VALUES ('cs_eski', 'kurtarma', 1, now() - interval '2 hours')");
        $this->oturum('cs_yeni', $this->ziyaret('a@example.com'), 1, true);
        // Başka uygulamada aynı kişi ayrı sayılır.
        $this->oturum('cs_baska', $this->ziyaret('a@example.com', true, 'baska-uygulama'), 1, true);

        self::assertSame(1, $this->dizi()->gonder());
        self::assertSame([['cs_baska', 1], ['cs_eski', 1]], $this->kayitlar());

        $this->db->executeStatement("UPDATE posta_dizisi SET gonderildi_at = now() - interval '8 days' WHERE kaynak = 'cs_eski'");
        self::assertSame(1, $this->dizi()->gonder(), '7 gün geçince yeni oturum dizisi başlar');
    }

    public function testBastirilmisEskiYaDaIzniGeriAlinanaGitmez(): void
    {
        $this->oturum('cs_1', $this->ziyaret('a@example.com'), 1, true);
        $this->bastir('a@example.com', Bastirma::YAYIN);
        $this->oturum('cs_2', $this->ziyaret('b@example.com'), 1, true);
        $this->bastir('b@example.com', Bastirma::ISLEM);
        $this->oturum('cs_3', $this->ziyaret('c@example.com'), 8 * 24, true);
        $z = $this->ziyaret('d@example.com');
        $this->oturum('cs_4', $z, 1, true);
        $this->db->executeStatement('UPDATE huni_epostalari SET pazarlama_izni_at = NULL WHERE ziyaret_id = ?', [$z]);

        self::assertSame(1, $this->dizi()->gonder());
        self::assertSame([['cs_2', 1]], $this->kayitlar(), 'yalnız işlem akışında bastırılmış olan alır');
    }

    public function testKurtarmaAdresiOlmayanaGitmez(): void
    {
        $z = $this->ziyaret('a@example.com');
        $this->oturum('cs_1', $z, 1, true);
        $this->db->executeStatement("UPDATE odeme_oturumlari SET kurtarma_url = NULL WHERE stripe_checkout_session_id = 'cs_1'");

        self::assertSame(0, $this->dizi()->gonder());
        self::assertSame([], $this->kayitlar(), 'adım da kaydedilmez');
    }

    public function testKomut(): void
    {
        $this->oturum('cs_1', $this->ziyaret('a@example.com'), 1, true);
        $tester = new \Symfony\Component\Console\Tester\CommandTester((new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel ?? self::fail('çekirdek yok')))->find('app:posta:dizi'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Kurtarma: 1 e-posta', $tester->getDisplay());
    }

    // --- yardımcılar ---

    private function dizi(): KurtarmaDizisi
    {
        $d = static::getContainer()->get(KurtarmaDizisi::class);
        self::assertInstanceOf(KurtarmaDizisi::class, $d);

        return $d;
    }

    private function ziyaret(string $email, bool $izin = true, string $app = 'keepchat'): string
    {
        $z = $this->db->fetchOne('INSERT INTO huni_ziyaretleri (app) VALUES (?) RETURNING id::text', [$app]);
        self::assertIsString($z);
        $this->db->executeStatement(
            'INSERT INTO huni_epostalari (ziyaret_id, eposta, pazarlama_izni_at) VALUES (?, ?, CASE WHEN ? THEN now() END)',
            [$z, $email, $izin], [2 => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return $z;
    }

    private function oturum(string $id, string $ziyaret, int $saatOnce, bool $doldu): void
    {
        $this->db->executeStatement(
            'INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id, created_at, kurtarma_url)
             VALUES (?, ?, now() - make_interval(hours => ?), CASE WHEN ? THEN ? END)',
            [$id, $ziyaret, $saatOnce, $doldu, self::URL],
            [3 => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
    }

    private function abone(string $id, string $email, string $durum, string $app = 'keepchat'): void
    {
        $this->db->executeStatement(
            "INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES (?, 'cus_1', ?, ?, ?)",
            [$id, $durum, $email, $app],
        );
    }

    private function bastir(string $email, string $akis): void
    {
        $b = static::getContainer()->get(Bastirma::class);
        self::assertInstanceOf(Bastirma::class, $b);
        $b->yaz($email, $akis, true, 'ManualSuppression', new \DateTimeImmutable());
    }

    /** @param array<string, mixed> $nesne */
    private function webhook(string $id, string $tur, array $nesne): void
    {
        // Stripe'ın belgelenmiş imza şeması, koddan bağımsız (tuzak §3).
        $govde = (string) json_encode(['id' => $id, 'object' => 'event', 'type' => $tur, 'created' => time(), 'data' => ['object' => $nesne]]);
        $t = time();
        $this->client->request('POST', '/webhooks/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$govde, 'whsec_test_gizli'),
        ], $govde);
        self::assertResponseStatusCodeSame(200);
    }

    /** @return list<array{mixed, mixed}> */
    private function kayitlar(): array
    {
        return array_map(
            static fn (array $r): array => [$r['kaynak'], $r['adim']],
            $this->db->fetchAllAssociative("SELECT kaynak, adim FROM posta_dizisi WHERE dizi = 'kurtarma' ORDER BY 1, 2"),
        );
    }

    private function say(string $sql): int
    {
        $n = $this->db->fetchOne($sql);

        return is_numeric($n) ? (int) $n : -1;
    }
}
