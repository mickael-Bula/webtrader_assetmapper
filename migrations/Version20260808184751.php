<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260808184751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajouter la colonne
        $this->addSql('ALTER TABLE "user" ADD is_verified BOOLEAN DEFAULT NULL');

        // 2. Remplir les données existantes à false
        $this->addSql('UPDATE "user" SET is_verified = FALSE WHERE is_verified IS NULL');

        // 3. Appliquer la contrainte NOT NULL
        $this->addSql('ALTER TABLE "user" ALTER COLUMN is_verified SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA market_data');
        $this->addSql('ALTER TABLE position ALTER sold_quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE "user" DROP is_verified');
    }
}
