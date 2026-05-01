<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501173230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ is_core dans la table position';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajout de la colonne avec la valeur TRUE par défaut
        $this->addSql('ALTER TABLE position ADD is_core BOOLEAN DEFAULT FALSE NOT NULL');

        // 2. Mise à jour explicite (optionnelle, mais sécurisante)
        $this->addSql('UPDATE position SET is_core = FALSE');
    }

    public function down(Schema $schema): void
    {
        // En cas de rollback, on supprime simplement la colonne
        $this->addSql('ALTER TABLE position DROP is_core');
    }
}
