<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M9c (A23): yeni hunilere kopyalanan varsayılan görünüm — bir huninin
 * temasının, logosunun ve son paywall adımının o anki kopyası. Kopya
 * modeli: varsayılan değişince var olan huniler değişmez.
 */
final class Version20261110240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M9c: yeni huni varsayılanları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE uygulama_ayarlari ADD varsayilan JSONB CHECK (varsayilan IS NULL OR jsonb_typeof(varsayilan) = 'object')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE uygulama_ayarlari DROP varsayilan');
    }
}
