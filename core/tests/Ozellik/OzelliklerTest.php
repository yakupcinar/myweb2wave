<?php

declare(strict_types=1);

namespace App\Tests\Ozellik;

use App\Atif\Ziyaretler;
use App\Huni\Huniler;
use App\Ozellik\Ozellikler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * T2: ziyaretin ve kişinin özellikleri tek ad uzayında. Beklenen haritalar
 * elle yazıldı (tuzak §3) — görünümün kendisinden hesaplanmaz.
 */
final class OzelliklerTest extends KernelTestCase
{
    private Connection $db;
    private Ziyaretler $ziyaretler;
    private Ozellikler $ozellikler;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, subscriptions, kisiler, baglantilar CASCADE');
        $ziyaretler = self::getContainer()->get(Ziyaretler::class);
        self::assertInstanceOf(Ziyaretler::class, $ziyaretler);
        $this->ziyaretler = $ziyaretler;
        $this->ozellikler = new Ozellikler($db);

        $huniler = self::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'kiminle', 'tur' => 'ekran', 'baslik' => 'Kiminle?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'aile', 'metin' => 'A'], ['id' => 'is', 'metin' => 'B']]]]],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]]));
        self::assertSame(1, $huniler->yayinla('keepchat', 'genel'));
    }

    public function testZiyaretinOzellikleriTekAdUzayinda(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', ['utm_source' => 'meta', 'utm_campaign' => 'yaz', 'utm_medium' => null, 'utm_content' => null, 'utm_term' => null, 'fbclid' => null, 'ttclid' => null], 'genel', 1, true);
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', null));
        self::assertTrue($this->ziyaretler->ilerle($z, 0, 'kiminle', ['aile']));
        self::assertTrue($this->ziyaretler->ilerle($z, 1, 'ilgi', ['yedek', 'arama']));
        self::assertTrue($this->ziyaretler->ilerle($z, 2, 'not', null, 'Daha az bildirim'));
        self::assertTrue($this->ziyaretler->ilerle($z, 3, 'paywall', null));

        self::assertSame([
            'adim.son' => 'paywall',
            'cevap.ilgi' => ['arama', 'yedek'],
            'cevap.kiminle' => ['aile'],
            'cevap.not' => 'Daha az bildirim',
            'huni' => 'genel',
            'huni.surum' => 1,
            'izin.reklam' => 'evet',
            'utm.campaign' => 'yaz',
            'utm.source' => 'meta',
        ], $this->ozellikler->ziyaretin($z));
    }

    /** Ödeme ve abonelik de özellik; izni reddeden ziyaret "hayir". */
    public function testOdemeAbonelikVeRedEdilmisIzin(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, false);
        self::assertTrue($this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_1', null, null));

        self::assertSame(['huni' => 'genel', 'huni.surum' => 1, 'izin.reklam' => 'hayir', 'odeme.durum' => 'baslatildi'], $this->ozellikler->ziyaretin($z));

        $this->db->executeStatement("UPDATE odeme_oturumlari SET tamamlandi_at = now(), stripe_subscription_id = 'sub_1' WHERE stripe_checkout_session_id = 'cs_1'");
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES ('sub_1', 'cus_1', 'active', 'ayse@example.com', 'keepchat')");

        $o = $this->ozellikler->ziyaretin($z);
        self::assertSame('tamamlandi', $o['odeme.durum'] ?? null);
        self::assertSame('active', $o['abonelik.durum'] ?? null);
    }

    /** Kişi: ödemeyle bağlanan ziyaretlerinin özellikleri, her adda en son değer. */
    public function testKisininOzellikleriZiyaretlerinBirlesimiEnSonKazanir(): void
    {
        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('ayse@example.com') RETURNING id");
        self::assertIsString($kisi);
        $eski = $this->ziyaretler->kaydet('keepchat', ['utm_source' => 'tiktok'] + array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, null);
        self::assertTrue($this->ziyaretler->ilerle($eski, 0, 'kiminle', ['is']));
        $yeni = $this->ziyaretler->kaydet('keepchat', ['utm_source' => 'meta'] + array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, null);
        self::assertTrue($this->ziyaretler->ilerle($yeni, 0, 'kiminle', ['aile']));
        $baska = $this->ziyaretler->kaydet('keepchat', ['utm_source' => 'google'] + array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, null);
        // Eski ziyaret önce; zaman sırası açık olsun.
        $this->db->executeStatement("UPDATE huni_ziyaretleri SET created_at = now() - interval '2 days' WHERE id = :id", ['id' => $eski]);
        $this->db->executeStatement("UPDATE huni_cevaplari SET updated_at = now() - interval '2 days' WHERE ziyaret_id = :id", ['id' => $eski]);
        foreach (['cs_e' => $eski, 'cs_y' => $yeni] as $oturum => $ziyaret) {
            self::assertTrue($this->ziyaretler->oturumBagla($ziyaret, 'keepchat', $oturum, null, null));
        }
        $this->db->executeStatement("UPDATE odeme_oturumlari SET tamamlandi_at = now(), stripe_subscription_id = 'sub_k'");
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES ('sub_k', 'cus_k', 'active', 'ayse@example.com', 'keepchat')");

        $o = $this->ozellikler->kisinin($kisi);

        self::assertSame('meta', $o['utm.source'] ?? null, 'en son ziyaretin kaynağı');
        self::assertSame(['aile'], $o['cevap.kiminle'] ?? null);
        self::assertSame('active', $o['abonelik.durum'] ?? null);
        self::assertNotContains('google', $o, 'bağlanmamış ziyaret kişiye karışmaz');
        self::assertSame([], $this->ozellikler->kisinin('00000000-0000-0000-0000-000000000000'));
        self::assertSame('google', $this->ozellikler->ziyaretin($baska)['utm.source'] ?? null, 'bağlanmamış ziyaretin kendi özellikleri var');
    }

    /** Sözlük eksiksiz: çıkan her ad sözlükteki bir kalıba uyar (T3 koşulları buna dayanır). */
    public function testCikanHerAdSozluktekiBirKaliba(): void
    {
        $z = $this->ziyaretler->kaydet('keepchat', array_fill_keys(Ziyaretler::ALANLAR, 'x'), 'genel', 1, true);
        $this->ziyaretler->ilerle($z, 0, 'kiminle', ['aile']);
        $this->ziyaretler->ilerle($z, 1, 'not', null, 'metin');
        $this->ziyaretler->oturumBagla($z, 'keepchat', 'cs_s', null, null);
        $this->db->executeStatement("UPDATE odeme_oturumlari SET tamamlandi_at = now(), stripe_subscription_id = 'sub_s'");
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES ('sub_s', 'cus_s', 'active', 's@example.com', 'keepchat')");

        $adlar = array_keys($this->ozellikler->ziyaretin($z));
        // huni, huni.surum, 5 utm, izin, 2 cevap, adim.son, odeme, abonelik.
        self::assertCount(13, $adlar);
        foreach ($adlar as $ad) {
            self::assertNotNull(Ozellikler::tanimi($ad), "sözlükte yok: {$ad}");
        }
        self::assertNull(Ozellikler::tanimi('cevap'), 'kalıp önek ister');
        self::assertNull(Ozellikler::tanimi('bilinmeyen.ad'));
    }

    /** Konsol: ziyaretin / kişinin özellikleri JSON, sözlük --sozluk ile. */
    public function testKonsolJsonVerir(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:ozellikler'));
        $z = $this->ziyaretler->kaydet('keepchat', ['utm_source' => 'meta'] + array_fill_keys(Ziyaretler::ALANLAR, null), 'genel', 1, null);

        self::assertSame(Command::SUCCESS, $komut->execute(['kimlik' => $z]));
        self::assertSame(['huni' => 'genel', 'huni.surum' => 1, 'utm.source' => 'meta'], json_decode($komut->getDisplay(), true));

        self::assertSame(Command::SUCCESS, $komut->execute(['--sozluk' => true]));
        $metin = $komut->getDisplay();
        $sozluk = json_decode($metin, true);
        self::assertIsArray($sozluk);
        self::assertSame(array_keys(Ozellikler::SOZLUK), array_keys($sozluk));

        self::assertSame(Command::INVALID, $komut->execute(['kimlik' => 'x']));
    }
}
