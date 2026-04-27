<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406171020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity column to position table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "position" ADD quantity INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "position" DROP quantity');
    }
}
