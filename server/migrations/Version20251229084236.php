<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251229084236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removing email';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_8d93d649e7927c74');
        $this->addSql('ALTER TABLE "user" DROP email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD email VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_8d93d649e7927c74 ON "user" (email)');
    }
}
