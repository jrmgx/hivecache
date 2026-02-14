<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251222105825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add domain to bookmark';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ADD domain VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP domain');
    }
}
