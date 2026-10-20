<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Etikete özel huni (A8): bir uygulamanın birden çok hunisi + yönlendirme kuralları.
 * - huniler: (app, ad); uygulama başına TAM BİR varsayılan (kısmi tekil dizin).
 * - huni_surumleri: (app, ad, surum).
 * - huni_kurallari: sıralı; adresteki parametre = değer → huni. İlk tutan kazanır.
 * - huni_ziyaretleri.huni: ziyaretin gördüğü huni (sürümle birlikte).
 * Var olan tek huni 'genel' adını alır ve varsayılan olur.
 */
final class Version20261020200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'cok huni (app, ad), varsayilan, huni_kurallari, huni_ziyaretleri.huni';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri DROP CONSTRAINT huni_ziyaretleri_app_surum_fkey');
        $this->addSql('ALTER TABLE huni_surumleri DROP CONSTRAINT huni_surumleri_app_fkey');
        $this->addSql('ALTER TABLE huni_surumleri DROP CONSTRAINT huni_surumleri_pkey');
        $this->addSql('ALTER TABLE huniler DROP CONSTRAINT huniler_pkey');

        $this->addSql("ALTER TABLE huniler ADD ad TEXT NOT NULL DEFAULT 'genel' CHECK (ad ~ '^[a-z0-9_-]{1,64}$')");
        $this->addSql('ALTER TABLE huniler ALTER ad DROP DEFAULT');
        $this->addSql('ALTER TABLE huniler ADD varsayilan BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('UPDATE huniler SET varsayilan = true');
        $this->addSql('ALTER TABLE huniler ADD PRIMARY KEY (app, ad)');
        $this->addSql('CREATE UNIQUE INDEX huniler_tek_varsayilan ON huniler (app) WHERE varsayilan');

        $this->addSql("ALTER TABLE huni_surumleri ADD ad TEXT NOT NULL DEFAULT 'genel'");
        $this->addSql('ALTER TABLE huni_surumleri ALTER ad DROP DEFAULT');
        $this->addSql('ALTER TABLE huni_surumleri ADD PRIMARY KEY (app, ad, surum)');
        $this->addSql('ALTER TABLE huni_surumleri ADD FOREIGN KEY (app, ad) REFERENCES huniler (app, ad)');

        $this->addSql('ALTER TABLE huni_ziyaretleri ADD huni TEXT');
        $this->addSql("UPDATE huni_ziyaretleri SET huni = 'genel' WHERE surum IS NOT NULL");
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD CHECK ((huni IS NULL) = (surum IS NULL))');
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD FOREIGN KEY (app, huni, surum) REFERENCES huni_surumleri (app, ad, surum)');

        $this->addSql("CREATE TABLE huni_kurallari (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            sira INT NOT NULL,
            parametre TEXT NOT NULL CHECK (parametre IN ('k', 'utm_source', 'utm_campaign', 'utm_content')),
            deger TEXT NOT NULL CHECK (deger = lower(deger) AND length(deger) BETWEEN 1 AND 200),
            huni TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            FOREIGN KEY (app, huni) REFERENCES huniler (app, ad),
            UNIQUE (app, parametre, deger)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('çok huni verisi tek huniye indirgenemez');
    }
}
