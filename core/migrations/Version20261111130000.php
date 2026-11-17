<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M9d1: yönlendirme kuralına ziyaretçinin ülkesi (IP, M1c) ve dili
 * (Accept-Language ya da ?dil=) koşulu. Değerleri iki küçük harf — "Türkiye"
 * gibi yazım hatası kuralı sessizce hiç tutmayan hâle getirmesin.
 */
final class Version20261111130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M9d1: kuralda ulke ve dil';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_kurallari DROP CONSTRAINT huni_kurallari_parametre_check');
        $this->addSql("ALTER TABLE huni_kurallari ADD CONSTRAINT huni_kurallari_parametre_check CHECK (parametre IN ('k', 'utm_source', 'utm_campaign', 'utm_content', 'ulke', 'dil'))");
        $this->addSql("ALTER TABLE huni_kurallari ADD CONSTRAINT huni_kurallari_bolge_check CHECK (parametre NOT IN ('ulke', 'dil') OR deger ~ '^[a-z]{2}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM huni_kurallari WHERE parametre IN ('ulke', 'dil')");
        $this->addSql('ALTER TABLE huni_kurallari DROP CONSTRAINT huni_kurallari_bolge_check');
        $this->addSql('ALTER TABLE huni_kurallari DROP CONSTRAINT huni_kurallari_parametre_check');
        $this->addSql("ALTER TABLE huni_kurallari ADD CONSTRAINT huni_kurallari_parametre_check CHECK (parametre IN ('k', 'utm_source', 'utm_campaign', 'utm_content'))");
    }
}
