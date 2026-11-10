<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M8a2 (A22): e-posta dizilerinin gönderim kaydı — abonelik × dizi × adım
 * bir kez. Yabancı anahtar yok: subscriptions'ı kesen (TRUNCATE) testler
 * ve olası yeniden içe aktarım bu kayda bağlanmasın; kayıt yalnız "gitti mi".
 */
final class Version20261110150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M8a2: posta dizisi gönderimleri';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE posta_dizisi (
            stripe_subscription_id TEXT NOT NULL,
            dizi TEXT NOT NULL,
            adim SMALLINT NOT NULL CHECK (adim > 0),
            gonderildi_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (stripe_subscription_id, dizi, adim)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE posta_dizisi');
    }
}
