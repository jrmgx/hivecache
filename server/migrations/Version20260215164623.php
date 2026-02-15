<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215164623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add active and motivations columns to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD motivations TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD active BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP motivations');
        $this->addSql('ALTER TABLE "user" DROP active');
    }
}
