<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use App\Atif\MetaBaglantisi;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use App\Uygulama\CanliyaGecis;
use App\Uygulama\GorevKaydi;
use App\Yasal\YasalMetinler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * P7c: "Canlıya geçiş" listesi elle işaretlenmez, çalışan sistemden ölçülür.
 * Stripe kipi anahtarın önekinden; bildirim = gizli dolu + işlenmiş olay;
 * e-posta = Postmark DSN; yasal = paywall belgeleri yayında; zamanlı görev =
 * beklenen aralıkta BAŞARIYLA çalışmış. Anahtarların kendisi yanıtta yok.
 */
final class CanliyaGecisTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE processed_webhook_events, yasal_metinler, zamanli_gorevler, webhook_teslimleri');
    }

    public function testUcTestOrtamindaOlculenDurumuVerir(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/canliya-gecis');
        self::assertResponseRedirects('/panel/giris');
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/yok/canliya-gecis');
        self::assertResponseStatusCodeSame(404);

        // .env.test: sk_test_ anahtar, whsec dolu, null:// posta; A30: Meta hesabı + anahtar panelden.
        $this->metaKaydet('act_11111', 'test_insights_token_0123456789');
        self::assertSame([
            'odeme' => ['tamam' => true, 'kip' => 'test'],
            'stripeBildirimleri' => ['tamam' => false, 'son' => null],
            'eposta' => ['tamam' => false],
            'yasal' => ['tamam' => false, 'eksikler' => ['sartlar', 'gizlilik']],
            'metaReklam' => ['tamam' => true],
            'gorevler' => ['tamam' => false, 'eksikler' => array_keys(CanliyaGecis::GOREVLER)],
        ], $this->durumIste());
        $govde = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('sk_test_stripemock', $govde);
        self::assertStringNotContainsString('whsec_test_gizli', $govde);
        self::assertStringNotContainsString('test_insights_token_0123456789', $govde);

        $this->db->insert('processed_webhook_events', ['event_id' => 'evt_1', 'type' => 'invoice.paid', 'processed_at' => '2026-11-10 09:00:00+00']);
        $yasal = static::getContainer()->get(YasalMetinler::class);
        self::assertInstanceOf(YasalMetinler::class, $yasal);
        // İngilizce yayında olması Türkçe paywall'ı karşılamaz.
        self::assertSame([], $yasal->kaydet('keepchat', 'gizlilik', 'en', ['metin' => 'Privacy']));
        self::assertSame([], $yasal->kaydet('keepchat', 'sartlar', 'tr', ['metin' => 'Şartlar']));
        $d = $this->durumIste();
        self::assertSame(['tamam' => true, 'son' => '2026-11-10T09:00:00+00:00'], $d['stripeBildirimleri']);
        self::assertSame(['tamam' => false, 'eksikler' => ['gizlilik']], $d['yasal']);

        self::assertSame([], $yasal->kaydet('keepchat', 'gizlilik', 'tr', ['metin' => 'Gizlilik']));
        self::assertSame(['tamam' => true, 'eksikler' => []], $this->durumIste()['yasal']);
    }

    public function testAnahtarKipiVeEksikAyarlar(): void
    {
        $this->db->insert('processed_webhook_events', ['event_id' => 'evt_1', 'type' => 'invoice.paid']);
        $simdi = new \DateTimeImmutable();

        $this->metaKaydet('act_11111', 'test_insights_token_0123456789');
        $canli = $this->servis('sk_live_x', 'whsec_x', 'postmark+api://anahtar@default')->durum('keepchat', $simdi);
        self::assertSame(['tamam' => true, 'kip' => 'canli'], $canli['odeme']);
        self::assertTrue($canli['eposta']['tamam']);
        self::assertTrue($canli['metaReklam']['tamam']);
        self::assertSame(['tamam' => true, 'kip' => 'canli'], $this->servis('rk_live_x')->durum('keepchat', $simdi)['odeme']);

        $this->db->executeStatement('TRUNCATE meta_baglantilari');
        $bos = $this->servis('', '', 'smtp://mailpit:1025')->durum('keepchat', $simdi);
        self::assertSame(['tamam' => false, 'kip' => null], $bos['odeme']);
        // Olay işlenmiş olsa da gizli yoksa imza doğrulanamaz: eksik.
        self::assertSame(['tamam' => false, 'son' => null], $bos['stripeBildirimleri']);
        self::assertFalse($bos['eposta']['tamam']);
        self::assertFalse($bos['metaReklam']['tamam']);
        $this->metaKaydet('act_11111', null);
        self::assertFalse($this->servis()->durum('keepchat', $simdi)['metaReklam']['tamam'], 'hesap var, anahtar yok');
    }

    public function testGorevBeklenenAraliktaCalismisOlmali(): void
    {
        $simdi = new \DateTimeImmutable('2026-11-11 12:00:00+00');
        foreach (CanliyaGecis::GOREVLER as $komut => $aralik) {
            $this->db->insert('zamanli_gorevler', ['komut' => $komut, 'son_calisma' => $simdi->modify("-{$aralik} seconds")->format('Y-m-d H:i:sP')]);
        }
        self::assertSame(['tamam' => true, 'eksikler' => []], $this->servis()->durum('keepchat', $simdi)['gorevler']);

        $this->db->update('zamanli_gorevler', ['son_calisma' => '2026-11-11 11:49:59+00'], ['komut' => 'app:webhook:gonder']);
        self::assertSame(['tamam' => false, 'eksikler' => ['app:webhook:gonder']], $this->servis()->durum('keepchat', $simdi)['gorevler']);
    }

    public function testZamanliKomutYalnizBasariylaBitinceYazilir(): void
    {
        $uygulama = new Application(self::$kernel ?? self::fail('çekirdek yok'));
        $uygulama->setAutoExit(false);
        self::assertSame(0, (new ApplicationTester($uygulama))->run(['command' => 'app:webhook:gonder']));
        $son = $this->db->fetchOne("SELECT son_calisma FROM zamanli_gorevler WHERE komut = 'app:webhook:gonder'");
        self::assertIsString($son);
        self::assertLessThan(60, time() - (new \DateTimeImmutable($son))->getTimestamp());

        $kayit = static::getContainer()->get(GorevKaydi::class);
        self::assertInstanceOf(GorevKaydi::class, $kayit);
        $kayit(new ConsoleTerminateEvent(new Command('app:posta:dizi'), new ArrayInput([]), new NullOutput(), 1));
        $kayit(new ConsoleTerminateEvent(new Command('app:olaylar'), new ArrayInput([]), new NullOutput(), 0));
        self::assertSame(['app:webhook:gonder'], $this->db->fetchFirstColumn('SELECT komut FROM zamanli_gorevler'));
    }

    private function servis(string $stripe = 'sk_test_x', string $webhook = 'whsec_x', string $dsn = 'null://null'): CanliyaGecis
    {
        $yasal = static::getContainer()->get(YasalMetinler::class);
        $meta = static::getContainer()->get(MetaBaglantisi::class);
        self::assertInstanceOf(YasalMetinler::class, $yasal);
        self::assertInstanceOf(MetaBaglantisi::class, $meta);

        return new CanliyaGecis($this->db, $yasal, $stripe, $webhook, $dsn, $meta);
    }

    private function metaKaydet(string $hesap, ?string $anahtar): void
    {
        $this->db->executeStatement('TRUNCATE meta_baglantilari');
        $meta = static::getContainer()->get(MetaBaglantisi::class);
        self::assertInstanceOf(MetaBaglantisi::class, $meta);
        self::assertSame([], $meta->kaydet('keepchat', ['reklamHesabi' => $hesap, 'insightsAnahtari' => $anahtar]));
    }

    /** @return array<string, mixed> */
    private function durumIste(): array
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/canliya-gecis');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/canliya-gecis', $this->client->getResponse());
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
