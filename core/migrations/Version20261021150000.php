<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reklam ölçümü izni (H6a, A12). Tıklama kimlikleri (fbclid, ttclid) yalnız
 * ziyaretçi onay şeridinde izin verdiyse saklanır; izin anı burada. Sunucu
 * dönüşümü (H6b) yalnız izinli ziyaretin ödemesini reklam ağına bildirir.
 */
final class Version20261021150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huni_ziyaretleri.izin_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD izin_at TIMESTAMPTZ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri DROP izin_at');
    }
}
