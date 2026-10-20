<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Huni panelden yönetilir (A7):
 * - huniler: uygulama başına TASLAK tanım (panel düzenler).
 * - huni_surumleri: yayınlanan her sürüm DEĞİŞMEZ anlık görüntü (v1, v2…);
 *   huni sayfası en son sürümü okur.
 * - huni_ziyaretleri.surum: ziyaretin gördüğü sürüm (sürümler arası karşılaştırma).
 * Tohum: o güne kadar kodda duran KeepChat hunisi (funnel/src/huniler/
 * keepchat.ts, TASLAK metin — Ç36) taslak + v1 olarak.
 */
final class Version20261020180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huniler, huni_surumleri, huni_ziyaretleri.surum + KeepChat v1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE huniler (
            app TEXT PRIMARY KEY,
            taslak JSONB NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE TABLE huni_surumleri (
            app TEXT NOT NULL REFERENCES huniler(app),
            surum INT NOT NULL CHECK (surum >= 1),
            tanim JSONB NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (app, surum)
        )');
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD surum INT');
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD FOREIGN KEY (app, surum) REFERENCES huni_surumleri (app, surum)');

        $tanim = json_encode(self::keepchat(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        $this->addSql('INSERT INTO huniler (app, taslak) VALUES (:app, CAST(:t AS jsonb))', ['app' => 'keepchat', 't' => $tanim]);
        $this->addSql('INSERT INTO huni_surumleri (app, surum, tanim) VALUES (:app, 1, CAST(:t AS jsonb))', ['app' => 'keepchat', 't' => $tanim]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri DROP COLUMN surum');
        $this->addSql('DROP TABLE huni_surumleri');
        $this->addSql('DROP TABLE huniler');
    }

    /** @return array<string, mixed> */
    private static function keepchat(): array
    {
        $secenekler = static fn (array $s): array => array_map(
            static fn (string $id, string $metin): array => ['id' => $id, 'metin' => $metin],
            array_keys($s),
            $s,
        );

        return [
            'sayfaBasligi' => 'KeepChat Pro',
            'adimlar' => [
                ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => "KeepChat'e hoş geldin",
                    'metin' => "Sade, birebir mesajlaşma — tarayıcıdan ve iPhone'dan. Seni biraz tanıyalım: üç kısa soru.",
                    'dugme' => 'Başlayalım'],
                ['id' => 'kiminle', 'tur' => 'soru', 'soru' => "KeepChat'i en çok kiminle kullanacaksın?",
                    'secenekler' => $secenekler(['aile' => 'Ailemle', 'arkadas' => 'Arkadaşlarımla', 'is' => 'İş için', 'bilmiyorum' => 'Henüz bilmiyorum'])],
                ['id' => 'cihaz', 'tur' => 'soru', 'soru' => 'Nereden yazışırsın?',
                    'secenekler' => $secenekler(['iphone' => "iPhone'dan", 'tarayici' => 'Bilgisayardan (tarayıcı)', 'ikisi' => 'İkisinden de'])],
                ['id' => 'oncelik', 'tur' => 'soru', 'soru' => 'Bir mesajlaşma uygulamasında senin için en önemlisi ne?',
                    'secenekler' => $secenekler(['sadelik' => 'Sadelik', 'hiz' => 'Hız', 'gizlilik' => 'Gizlilik', 'diger' => 'Başka bir şey'])],
                ['id' => 'hazir', 'tur' => 'tanitim', 'baslik' => 'Teşekkürler!',
                    'metin' => 'KeepChat Pro aboneliğiyle uygulamanın geliştirilmesini destekleyebilirsin. Ödemeden sonra uygulamada Ayarlar → Abonelik → Aboneliğimi bağla ile hesabına bağlarsın.',
                    'dugme' => 'Planı gör'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'KeepChat Pro', 'metin' => 'Aylık plan · 9,99 € / ay',
                    'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
    }
}
