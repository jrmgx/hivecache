<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251228180457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Security migration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD security_invalidation VARCHAR(255) DEFAULT \'initial\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP security_invalidation');
    }
}
