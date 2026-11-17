<?php

declare(strict_types=1);

namespace App\Tests\Olay;

use App\Atif\Ziyaretler;
use App\Olay\GidenWebhook;
use App\Olay\Olaylar;
use App\Tests\Support\SahteRevenueCat;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * M7a (A20): giden webhook — hedef başına imleç (kuruluştan önceki olaylar
 * gitmez, seçilmeyen ad geçilir, yarım işlem beklenir), Standard Webhooks
 * imzası (beklenen değer spesifikasyonun örneğinden, Python'la doğrulandı —
 * tuzak §3), en az bir kez teslim ve üstel bekleme, iç ağ adresi yok.
 */
final class GidenWebhookTest extends KernelTestCase
{
    private Connection $db;
    private GidenWebhook $webhook;
    private Ziyaretler $ziyaretler;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE webhook_hedefleri, webhook_teslimleri, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri CASCADE');
        $w = self::getContainer()->get(GidenWebhook::class);
        self::assertInstanceOf(GidenWebhook::class, $w);
        $this->webhook = $w;
        $z = self::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $z);
        $this->ziyaretler = $z;
        SahteRevenueCat::sifirla();
    }

    private function ziyaret(string $app = 'keepchat'): string
    {
        return $this->ziyaretler->kaydet($app, array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
    }

    /**
     * Adres genel bir IP: NoPrivateNetwork alan adını kendisi çözer, testte DNS
     * olmasın (istek yine sahteye gider). Belge aralıkları (TEST-NET) da engelli.
     *
     * @param list<string> $olaylar
     */
    private function hedef(array $olaylar, string $adres = 'https://93.184.215.14/kanca', bool $aktif = true): string
    {
        $id = $this->db->fetchOne(
            'INSERT INTO webhook_hedefleri (app, adres, olaylar, sir, aktif) VALUES (:app, :adres, CAST(:olaylar AS text[]), :sir, :aktif) RETURNING id',
            ['app' => 'keepchat', 'adres' => $adres, 'olaylar' => '{'.implode(',', $olaylar).'}', 'sir' => 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', 'aktif' => $aktif],
            ['aktif' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        self::assertIsString($id);

        return $id;
    }

    /** @return list<array{string, string}> [olay adı, durum] */
    private function teslimler(): array
    {
        /** @var list<array{ad: string, durum: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative('SELECT o.ad, t.durum FROM webhook_teslimleri t JOIN olaylar o ON o.id = t.olay_id ORDER BY o.islem, o.id');

        return array_map(static fn (array $t): array => [$t['ad'], $t['durum']], $satirlar);
    }

    /** Spesifikasyonun örneği: aynı sır, kimlik, zaman ve gövde → aynı imza. */
    public function testStandardWebhooksImzasi(): void
    {
        self::assertSame('v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=', GidenWebhook::imza('whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', 'msg_p5jXN8AQM9LWM0D4loKWxJek', 1614265330, '{"test": 2432232314}'));
        self::assertMatchesRegularExpression('#^whsec_[A-Za-z0-9+/]{32}$#', GidenWebhook::yeniSir());
    }

    /** Kuruluştan önceki olay gitmez; seçilen adlar bir kez; başka uygulama ve seçilmeyen ad yok. */
    public function testImlecVeSecim(): void
    {
        // Kuruluştan önceki olay seçilen adda — yine de gitmez (kırma G16).
        self::assertTrue($this->ziyaretler->izinVer($this->ziyaret(), []));
        $this->hedef(['izin.verildi', 'izin.reddedildi']);
        $z = $this->ziyaret();
        $this->ziyaret('baska');
        self::assertTrue($this->ziyaretler->izinVer($z, []));
        self::assertTrue($this->ziyaretler->izinGeriAl($z));

        self::assertSame(2, $this->webhook->kuyruga());
        self::assertSame(0, $this->webhook->kuyruga(), 'ikinci tarama aynı olayı yeniden sıraya koymaz');
        self::assertSame([['izin.verildi', 'bekliyor'], ['izin.reddedildi', 'bekliyor']], $this->teslimler());
    }

    /** Yarım işlemin olayı beklenir, imleç onu geçmez; işlem bitince gelir (tuzak §53). */
    public function testYarimIslemBeklenir(): void
    {
        $this->hedef(['izin.verildi', 'izin.reddedildi']);
        $z = $this->ziyaret();
        $t = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $t->beginTransaction();
        $t->fetchOne('SELECT pg_current_xact_id()');
        Olaylar::ekle($t, $z, 'izin.verildi');
        Olaylar::ekle($this->db, $z, 'izin.reddedildi');

        self::assertSame(0, $this->webhook->kuyruga());
        $t->commit();
        $t->close();
        self::assertSame(2, $this->webhook->kuyruga());
        self::assertSame([['izin.verildi', 'bekliyor'], ['izin.reddedildi', 'bekliyor']], $this->teslimler());
    }

    /**
     * Eski işlem (A) yarım işlemden (T) sonra ekleyip ÖNCE biter: A sıraya girer,
     * imleç (işlem, id) A'da durur; T bitince küçük id'li olayı da gelir. id
     * imleci onu kalıcı atlardı.
     */
    public function testEskiIslemOnceBiterseYarimIslemAtlanmaz(): void
    {
        $this->hedef(['izin.verildi', 'izin.reddedildi']);
        $z = $this->ziyaret();
        $a = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $t = new Connection($this->db->getParams(), $this->db->getDriver(), $this->db->getConfiguration());
        $a->beginTransaction();
        $a->fetchOne('SELECT pg_current_xact_id()');
        $t->beginTransaction();
        $t->fetchOne('SELECT pg_current_xact_id()');
        Olaylar::ekle($t, $z, 'izin.verildi');
        Olaylar::ekle($a, $z, 'izin.reddedildi');
        $a->commit();

        self::assertSame(1, $this->webhook->kuyruga());
        $t->commit();
        $a->close();
        $t->close();
        self::assertSame(1, $this->webhook->kuyruga());
        self::assertSame([['izin.reddedildi', 'bekliyor'], ['izin.verildi', 'bekliyor']], $this->teslimler());
    }

    /** Gövde yalnız kimlik + sözlük verisi (kişisel veri yok); imza başlıkları; 2xx teslim. */
    public function testImzaliTeslim(): void
    {
        $this->hedef(['huni.ziyaret']);
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), null, null, null);
        $this->webhook->kuyruga();

        self::assertSame(['teslim' => 1, 'basarisiz' => 0], $this->webhook->gonder());
        $istek = SahteRevenueCat::istek(0);
        self::assertSame(['POST', 'https://93.184.215.14/kanca'], [$istek['method'], $istek['url']]);
        $govde = json_decode($istek['body'], true);
        self::assertIsArray($govde);
        self::assertSame(['id', 'ad', 'ziyaret', 'veri', 'zaman'], array_keys($govde));
        self::assertSame(['huni.ziyaret', $z], [$govde['ad'], $govde['ziyaret']]);
        $baslik = static function (string $ad) use ($istek): ?string {
            foreach ($istek['headers'] as $h) {
                if (str_starts_with(strtolower($h), $ad.':')) {
                    return trim(substr($h, \strlen($ad) + 1));
                }
            }

            return null;
        };
        self::assertIsInt($govde['id']);
        self::assertSame('olay_'.$govde['id'], $baslik('webhook-id'));
        self::assertSame(GidenWebhook::imza('whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', 'olay_'.$govde['id'], (int) $baslik('webhook-timestamp'), $istek['body']), $baslik('webhook-signature'));
        self::assertSame([['huni.ziyaret', 'teslim']], $this->teslimler());
        self::assertSame(['teslim' => 0, 'basarisiz' => 0], $this->webhook->gonder(), 'teslim edilen yeniden gitmez');
    }

    /** 2xx değilse üstel bekleme; zamanı gelmeyen gitmez; AZAMI_DENEME'de vazgeçilir. Pasif hedefe gitmez. */
    public function testBeklemeVeVazgecme(): void
    {
        $this->hedef(['huni.ziyaret']);
        $this->ziyaret();
        $this->webhook->kuyruga();
        SahteRevenueCat::$durum = 500;

        self::assertSame(['teslim' => 0, 'basarisiz' => 1], $this->webhook->gonder());
        self::assertSame([1, 'HTTP 500', true], array_values((array) $this->db->fetchAssociative(
            "SELECT deneme, son_hata, sonraki_deneme_at BETWEEN now() + interval '29 seconds' AND now() + interval '31 seconds' FROM webhook_teslimleri",
        )));
        self::assertSame(['teslim' => 0, 'basarisiz' => 0], $this->webhook->gonder(), 'zamanı gelmedi');
        // Üstel: 4. denemenin ardından 30 × 2³ = 240 sn.
        $this->db->executeStatement('UPDATE webhook_teslimleri SET deneme = 3, sonraki_deneme_at = now()');
        $this->webhook->gonder();
        self::assertTrue($this->db->fetchOne("SELECT sonraki_deneme_at BETWEEN now() + interval '239 seconds' AND now() + interval '241 seconds' FROM webhook_teslimleri"));
        $this->db->executeStatement('UPDATE webhook_teslimleri SET deneme = :d, sonraki_deneme_at = now()', ['d' => GidenWebhook::AZAMI_DENEME - 1]);
        $this->webhook->gonder();
        self::assertSame([['huni.ziyaret', 'vazgecildi']], $this->teslimler());

        $this->db->executeStatement("UPDATE webhook_teslimleri SET durum = 'bekliyor', sonraki_deneme_at = now()");
        $this->db->executeStatement('UPDATE webhook_hedefleri SET aktif = false');
        self::assertSame(['teslim' => 0, 'basarisiz' => 0], $this->webhook->gonder());
    }

    /** İç ağ adresine istek gitmez (panelden girilen adres sunucunun içine dokunmasın). */
    public function testIcAgaGitmez(): void
    {
        $this->hedef(['huni.ziyaret'], 'http://127.0.0.1:8080/ic');
        $this->ziyaret();
        $this->webhook->kuyruga();

        self::assertSame(['teslim' => 0, 'basarisiz' => 1], $this->webhook->gonder());
        self::assertSame(0, SahteRevenueCat::sayi());
        $hata = $this->db->fetchOne('SELECT son_hata FROM webhook_teslimleri');
        self::assertIsString($hata);
        self::assertStringStartsWith('bağlantı hatası', $hata);
    }

    public function testKonsol(): void
    {
        $this->hedef(['huni.ziyaret']);
        $this->ziyaret();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:webhook:gonder'));

        self::assertSame(Command::SUCCESS, $komut->execute([]));
        self::assertStringContainsString('Kuyruğa: 1; teslim: 1; başarısız: 0', $komut->getDisplay());
    }
}
