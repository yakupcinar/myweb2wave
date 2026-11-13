<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T2b: seçenek listesi bayt sırasıyla (COLLATE "C") — veritabanının en_US
 * sıralaması "a-1 a_1 a1" der, oynatıcı (JS) "a-1 a1 a_1"; iki taraf aynı
 * listeyi üretmeli (ölçüldü: OzellikEsligiTest ilk koşuda kırmızı).
 */
final class Version20261102140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T2b: ziyaret_ozellikleri seçenek sıralaması COLLATE "C"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE OR REPLACE VIEW ziyaret_ozellikleri (ziyaret_id, ad, deger, zaman) AS '.Version20261102130000::sorgu(' COLLATE "C"'));
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE OR REPLACE VIEW ziyaret_ozellikleri (ziyaret_id, ad, deger, zaman) AS '.Version20261102130000::sorgu(''));
    }
}
