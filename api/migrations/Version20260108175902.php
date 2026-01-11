<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108175902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Instance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD instance VARCHAR(255) DEFAULT \'bookmarkhive.test\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP instance');
    }
}
