<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251231080041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unique constraint on owner tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX unique_owner_slug ON tag (owner_id, slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX unique_owner_slug');
    }
}
