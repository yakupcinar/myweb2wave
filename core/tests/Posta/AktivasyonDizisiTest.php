<?php

declare(strict_types=1);

namespace App\Tests\Posta;

use App\Kimlik\GirisBaglantisi;
use App\Posta\AktivasyonDizisi;
use App\Posta\Bastirma;
use App\Uygulama\UygulamaAyarlari;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * M8a2 (A22): ödeyip bağlanmayana hatırlatma. Zamanı gelen en son adım
 * gider (öncekiler atlanır), her adım bir kez, yeni giriş bağlantısıyla;
 * bağlanınca / erişim bitince / adres bastırılınca / ödeme çok eskiyse durur.
 */
final class AktivasyonDizisiTest extends KernelTestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE posta_dizisi, posta_bastirma, giris_jetonlari, baglantilar, kisiler, subscriptions CASCADE');
        $this->db->executeStatement("UPDATE uygulama_ayarlari SET magaza_adresi = '' WHERE app = 'keepchat'");
    }

    public function testZamaniGelenIlkAdimBirKezGiderYeniBaglantiyla(): void
    {
        $this->abonelik('sub_1', 'A@Example.com', 25);
        $this->abonelik('sub_erken', 'b@example.com', 23);

        self::assertSame(1, $this->dizi()->gonder());
        self::assertEmailCount(1);
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertSame('A@Example.com', $mesaj->getTo()[0]->getAddress());
        self::assertSame('Aboneliğiniz hazır — uygulamaya bağlanın', $mesaj->getSubject());
        self::assertSame('KeepChat', $mesaj->getFrom()[0]->getName(), 'M9a: gönderen adı ayarlardan');
        $govde = (string) $mesaj->getTextBody();
        self::assertMatchesRegularExpression('#keepchat://giris\?jeton=[A-Za-z0-9_-]{43}#', $govde);
        self::assertStringNotContainsString('https://', $govde, 'mağaza adresi boşken yazılmaz');
        self::assertSame(1, $this->say("SELECT count(*) FROM giris_jetonlari WHERE stripe_checkout_session_id = 'cs_sub_1'"));
        self::assertSame([['sub_1', 1]], $this->kayitlar());

        self::assertSame(0, $this->dizi()->gonder(), 'aynı adım ikinci kez gitmez');
    }

    public function testGecikmisAdimlarUstUsteGitmezYalnizSonuncusu(): void
    {
        $this->abonelik('sub_1', 'a@example.com', 73);

        self::assertSame(1, $this->dizi()->gonder());
        self::assertSame([['sub_1', 2]], $this->kayitlar());
        self::assertSame(0, $this->dizi()->gonder(), 'atlanan 1. adım sonradan gitmez');
    }

    public function testIkinciAdimIlkindenSonraZamaniGelinceGider(): void
    {
        $this->abonelik('sub_1', 'a@example.com', 73);
        $this->db->executeStatement("INSERT INTO posta_dizisi (kaynak, dizi, adim) VALUES ('sub_1', 'aktivasyon', 1)");

        self::assertSame(1, $this->dizi()->gonder());
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertSame('Aboneliğinizi henüz kullanmadınız', $mesaj->getSubject());
        self::assertSame([['sub_1', 1], ['sub_1', 2]], $this->kayitlar());
    }

    public function testUygulamayaBaglananaGitmezBaskaUygulamadakiBagSayilmaz(): void
    {
        $this->abonelik('sub_bagli', 'Bagli@Example.com', 25);
        $this->abonelik('sub_baska', 'baska@example.com', 25);
        $this->bagla('keepchat', 'bagli@example.com');
        $this->bagla('baska-uygulama', 'baska@example.com');

        self::assertSame(1, $this->dizi()->gonder());
        self::assertSame([['sub_baska', 1]], $this->kayitlar());
    }

    public function testErisimVermeyenAboneligeGitmez(): void
    {
        $this->abonelik('sub_iptal', 'a@example.com', 25, 'canceled');
        $this->abonelik('sub_deneme', 'b@example.com', 25, 'trialing');
        $this->abonelik('sub_gecikmis', 'c@example.com', 25, 'past_due');

        self::assertSame(2, $this->dizi()->gonder());
        self::assertSame([['sub_deneme', 1], ['sub_gecikmis', 1]], $this->kayitlar());
    }

    public function testBastirilmisEskiYaDaIlkEpostasiGitmemisAboneligeGitmez(): void
    {
        $this->abonelik('sub_bastirilmis', 'a@example.com', 25);
        $bastirma = static::getContainer()->get(Bastirma::class);
        self::assertInstanceOf(Bastirma::class, $bastirma);
        $bastirma->yaz('a@example.com', Bastirma::ISLEM, true, 'HardBounce', new \DateTimeImmutable());
        // 7 günden eski ödeme: özellik açılınca birikmiş listeye toplu gönderim olmasın.
        $this->abonelik('sub_eski', 'b@example.com', 8 * 24);
        $this->abonelik('sub_sinir', 'c@example.com', 7 * 24 - 1);
        $this->abonelik('sub_ilk_yok', 'd@example.com', 25);
        $this->db->executeStatement("UPDATE subscriptions SET giris_epostasi_at = NULL WHERE stripe_subscription_id = 'sub_ilk_yok'");

        self::assertSame(1, $this->dizi()->gonder());
        self::assertSame([['sub_sinir', 2]], $this->kayitlar());
    }

    public function testMagazaAdresiVarsaYazilir(): void
    {
        $this->abonelik('sub_1', 'a@example.com', 25);

        $this->db->executeStatement("UPDATE uygulama_ayarlari SET magaza_adresi = 'https://apps.apple.com/app/id123' WHERE app = 'keepchat'");

        self::assertSame(1, $this->dizi()->gonder());
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertStringContainsString("kurun:\nhttps://apps.apple.com/app/id123", (string) $mesaj->getTextBody());
    }

    public function testGonderilemeyenAdimKaydedilmezSonraYenidenDenenir(): void
    {
        $this->abonelik('sub_1', 'a@example.com', 25);
        $dusen = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new \RuntimeException('sağlayıcı yanıt vermedi');
            }
        };

        self::assertSame(0, $this->dizi($dusen)->gonder());
        self::assertSame([], $this->kayitlar());
        self::assertSame(0, $this->say('SELECT count(*) FROM giris_jetonlari'), 'yarım kalan bağlantı da geri alınır');

        self::assertSame(1, $this->dizi()->gonder());
    }

    public function testKomut(): void
    {
        $this->abonelik('sub_1', 'a@example.com', 25);
        $tester = new \Symfony\Component\Console\Tester\CommandTester((new \Symfony\Bundle\FrameworkBundle\Console\Application(self::$kernel ?? self::fail('çekirdek yok')))->find('app:posta:dizi'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('Aktivasyon: 1 e-posta', $tester->getDisplay());
    }

    private function dizi(?MailerInterface $mailer = null): AktivasyonDizisi
    {
        if (null === $mailer) {
            $d = static::getContainer()->get(AktivasyonDizisi::class);
            self::assertInstanceOf(AktivasyonDizisi::class, $d);

            return $d;
        }
        $giris = static::getContainer()->get(GirisBaglantisi::class);
        $ayarlar = static::getContainer()->get(UygulamaAyarlari::class);
        self::assertInstanceOf(GirisBaglantisi::class, $giris);
        self::assertInstanceOf(UygulamaAyarlari::class, $ayarlar);

        return new AktivasyonDizisi($this->db, $mailer, $giris, new NullLogger(), $ayarlar);
    }

    private function abonelik(string $id, string $email, int $saatOnce, string $durum = 'active'): void
    {
        $this->db->executeStatement(
            "INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app, stripe_checkout_session_id, odeme_tamamlandi_at, giris_epostasi_at)
             VALUES (?, 'cus_1', ?, ?, 'keepchat', ?, now() - make_interval(hours => ?), now() - make_interval(hours => ?))",
            [$id, $durum, $email, 'cs_'.$id, $saatOnce, $saatOnce],
        );
    }

    private function bagla(string $app, string $email): void
    {
        $kisi = $this->db->fetchOne('INSERT INTO kisiler (email) VALUES (?) ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email RETURNING id', [$email]);
        $this->db->executeStatement("INSERT INTO baglantilar (app, harici_kullanici, kisi_id) VALUES (?, 'u1', ?)", [$app, $kisi]);
    }

    /** @return list<array{mixed, mixed}> */
    private function kayitlar(): array
    {
        return array_map(
            static fn (array $r): array => [$r['kaynak'], $r['adim']],
            $this->db->fetchAllAssociative('SELECT kaynak, adim FROM posta_dizisi ORDER BY 1, 2'),
        );
    }

    private function say(string $sql): int
    {
        $n = $this->db->fetchOne($sql);

        return is_numeric($n) ? (int) $n : -1;
    }
}
