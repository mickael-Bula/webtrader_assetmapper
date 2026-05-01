<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501180131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modifie la colonne target_price de la table position pour autoriser les NULL';
    }

    public function up(Schema $schema): void
    {
        // On modifie la colonne pour autoriser le NULL
        $this->addSql('ALTER TABLE position ALTER target_price DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // En cas de rollback, on remet la contrainte (attention, s'il y a des NULL en base, cela échouera).
        $this->addSql('ALTER TABLE position ALTER target_price SET NOT NULL');
    }
}
