<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ç35/Ç41: panel oturumları veritabanında (Symfony PdoSessionHandler'ın
 * pgsql şeması) — kabın dosya sisteminde değil; dağıtımda yönetici çıkış
 * yapmış olmaz.
 */
final class Version20261026120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Panel oturumları tablosu (PdoSessionHandler)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessions (
            sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
            sess_data BYTEA NOT NULL,
            sess_lifetime INTEGER NOT NULL,
            sess_time INTEGER NOT NULL
        )');
        $this->addSql('CREATE INDEX sessions_sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}
