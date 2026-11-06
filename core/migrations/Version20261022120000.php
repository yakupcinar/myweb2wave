<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Her soru için birden fazla seçenek yanıtını saklar; eski tekil yanıtlar korunur. */
final class Version20261022120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huni_cevaplari soru başına birden fazla seçenek';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_cevaplari DROP CONSTRAINT huni_cevaplari_pkey');
        $this->addSql('ALTER TABLE huni_cevaplari ADD PRIMARY KEY (ziyaret_id, soru, secenek)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (bool) $this->connection->fetchOne('SELECT EXISTS (
                SELECT 1 FROM huni_cevaplari GROUP BY ziyaret_id, soru HAVING count(*) > 1
            )'),
            'Birden fazla yanıtı olan soru kaydı var; tekil şemaya veri kaybetmeden dönülemez.',
        );
        $this->addSql('ALTER TABLE huni_cevaplari DROP CONSTRAINT huni_cevaplari_pkey');
        $this->addSql('ALTER TABLE huni_cevaplari ADD PRIMARY KEY (ziyaret_id, soru)');
    }
}
