<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * BR2: "Yapay zekâ için kopyala" — uygulamanın kod tabanındaki yapay zekâya
 * verilen talimat. Kararlar: (1) gizli değer YOK, anahtar yer tutucu — metin
 * sohbete/başka depoya yapıştırılır; (2) uçlar ve şema bu uygulamaya özel;
 * (3) "Şu an eksik" çalışan sistemden ölçülür (BR1 ile aynı kaynak), elle değil.
 */
final class EntegrasyonRehberiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    /** @var array<string, mixed>|null */
    private ?array $yedek;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE uygulama_istekleri, revenuecat_gidenler, baglantilar, giris_jetonlari, kisiler, subscriptions CASCADE');
        // uygulama_ayarlari tohumdur (başka testler şemayı ister): boşaltılmaz, test sonunda geri yazılır.
        $satir = $this->db->fetchAssociative("SELECT * FROM uygulama_ayarlari WHERE app = 'keepchat'");
        $this->yedek = false === $satir ? null : $satir;
    }

    protected function tearDown(): void
    {
        $this->db->executeStatement("DELETE FROM uygulama_ayarlari WHERE app = 'keepchat'");
        if (null !== $this->yedek) {
            $this->db->insert('uygulama_ayarlari', $this->yedek);
        }
        parent::tearDown();
    }

    public function testGirisVeBilinmeyenUygulama(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/rehber');
        self::assertResponseRedirects('/panel/giris');
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/yok/rehber');
        self::assertResponseStatusCodeSame(404);
    }

    public function testGizliDegerYokUclarUygulamayaOzel(): void
    {
        $this->girisYap();
        $this->db->executeStatement("DELETE FROM uygulama_ayarlari WHERE app = 'keepchat'");
        $this->db->insert('uygulama_ayarlari', ['app' => 'keepchat', 'ad' => 'KeepChat', 'sema' => 'keepchatx', 'magaza_adresi' => '', 'destek_epostasi' => '']);
        $metin = $this->rehberIste();

        foreach (['test-anahtar-keepchat', 'strp_test_revenuecat', 'sk_test_v2', 'projtest'] as $gizli) {
            self::assertStringNotContainsString($gizli, $metin);
        }
        self::assertStringContainsString('Authorization: Bearer <UYGULAMA_ANAHTARI>', $metin);
        self::assertStringContainsString('# KeepChat → MyWeb2Wave', $metin);
        foreach (['/apps/keepchat/baglama/kod', '/apps/keepchat/baglama/dogrula', '/apps/keepchat/kullanicilar/{kullanici}/hak', '/apps/keepchat/giris/kullan'] as $uc) {
            self::assertStringContainsString($uc, $metin);
        }
        self::assertStringContainsString('`keepchatx://giris?jeton=<jeton>`', $metin);
        self::assertStringContainsString('Purchases.logIn(kisi)', $metin, '.env.test: RevenueCat aktarımı açık');
    }

    public function testSemaBosken(): void
    {
        $this->girisYap();
        $this->db->executeStatement("DELETE FROM uygulama_ayarlari WHERE app = 'keepchat'");
        self::assertStringContainsString('henüz boş, önce orada girilmeli>://giris', $this->rehberIste());
    }

    /** Eksik listesi BR1'in ölçtüğünden gelir: bağlama olunca o madde düşer. */
    public function testEksikAdimlarOlculur(): void
    {
        $this->girisYap();
        $once = $this->rehberIste();
        self::assertStringContainsString('Henüz kimse bağlanmadı', $once);
        self::assertStringContainsString('hiç istek gelmedi', $once);
        self::assertStringNotContainsString('Uygulama anahtarı çekirdekte boş', $once, '.env.test: anahtar dolu');

        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('a@example.com') RETURNING id");
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'u1', 'kisi_id' => $kisi]);
        $sonra = $this->rehberIste();
        self::assertStringNotContainsString('Henüz kimse bağlanmadı', $sonra);
        self::assertStringContainsString('hiç istek gelmedi', $sonra);
    }

    private function rehberIste(): string
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/rehber');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/rehber', $this->client->getResponse());
        $d = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($d);
        self::assertIsString($d['metin']);

        return $d['metin'];
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
