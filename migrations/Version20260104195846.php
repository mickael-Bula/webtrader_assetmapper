<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104195846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Supprime une contrainte suite à la suppression de la relation entre les tables User et CacDaily.";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT fk_8d93d6495d69e1f5');
        $this->addSql('DROP INDEX idx_8d93d6495d69e1f5');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA market_data');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT fk_8d93d6495d69e1f5 FOREIGN KEY (last_cac_updated_id) REFERENCES market_data.cac_daily (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8d93d6495d69e1f5 ON "user" (last_cac_updated_id)');
    }
}
