<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Huni anketi (A6): ziyaretin ulaştığı her adım ve verdiği cevaplar.
 * - huni_adimlari: (ziyaret, sıra) başına bir satır — "kaç kişi bu adıma
 *   ulaştı" doğrudan sayılır; geri gidip yeniden gelmek ikinci satır açmaz.
 * - huni_cevaplari: (ziyaret, soru) başına bir satır — geri gidip
 *   değiştirilen cevap öncekini ezer.
 * Adım/soru adlarını huni tanımı (funnel/src/huniler/) belirler; çekirdek
 * yalnız biçimi denetler.
 */
final class Version20261020150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huni_adimlari, huni_cevaplari';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE huni_adimlari (
            ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri(id),
            sira INT NOT NULL CHECK (sira >= 0),
            adim TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (ziyaret_id, sira)
        )');
        $this->addSql('CREATE TABLE huni_cevaplari (
            ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri(id),
            soru TEXT NOT NULL,
            secenek TEXT NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (ziyaret_id, soru)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE huni_cevaplari');
        $this->addSql('DROP TABLE huni_adimlari');
    }
}
