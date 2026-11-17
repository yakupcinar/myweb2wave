<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * V3b: stok görselin kaynağı ve lisansı görselle birlikte saklanır —
 * "nereden geldi, hangi lisansla" sorusu her zaman cevaplanabilsin. Yalnız
 * lisansı ölçülmüş kaynak (pexels); kaynaklıysa bütün alanlar dolu.
 */
final class Version20261103160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'V3b: medya kaynak ve lisans alanları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE medya
                ADD COLUMN kaynak TEXT CHECK (kaynak IN ('pexels')),
                ADD COLUMN kaynak_adresi TEXT,
                ADD COLUMN yazar TEXT,
                ADD COLUMN yazar_adresi TEXT,
                ADD COLUMN lisans TEXT,
                ADD COLUMN lisans_adresi TEXT,
                ADD CONSTRAINT medya_kaynak_tam CHECK (kaynak IS NULL OR (kaynak_adresi IS NOT NULL AND yazar IS NOT NULL AND lisans IS NOT NULL AND lisans_adresi IS NOT NULL))
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medya DROP CONSTRAINT medya_kaynak_tam, DROP COLUMN kaynak, DROP COLUMN kaynak_adresi, DROP COLUMN yazar, DROP COLUMN yazar_adresi, DROP COLUMN lisans, DROP COLUMN lisans_adresi');
    }
}
