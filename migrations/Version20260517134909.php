<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260517134909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs initial_quantity et sold_quantity dans la table position.';
    }

    public function up(Schema $schema): void
    {
        // Étape 1 : On crée les deux colonnes en acceptant le NULL (pas de blocage à la création)
        $this->addSql('ALTER TABLE position ADD initial_quantity INT DEFAULT NULL');
        $this->addSql('ALTER TABLE position ADD sold_quantity INT DEFAULT NULL');

        // Étape 2 : On initialise TOUTES les lignes existantes pour injecter les bonnes valeurs
        $this->addSql('UPDATE position SET initial_quantity = quantity');
        $this->addSql('UPDATE position SET sold_quantity = 0');

        // Étape 3 : Sécurité pour nettoyer les "quantity" potentiellement nulles avant de verrouiller
        $this->addSql('UPDATE position SET quantity = 1 WHERE quantity IS NULL');
        $this->addSql('UPDATE position SET initial_quantity = 1 WHERE initial_quantity IS NULL');

        // Étape 4 : Maintenant que 100% des lignes ont une valeur numérique, on force le NOT NULL en base
        $this->addSql('ALTER TABLE position ALTER COLUMN initial_quantity SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER COLUMN sold_quantity SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER COLUMN quantity SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position DROP initial_quantity');
        $this->addSql('ALTER TABLE position DROP sold_quantity');
        $this->addSql('ALTER TABLE position ALTER COLUMN quantity DROP NOT NULL');
    }
}
