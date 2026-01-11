<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108194405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT fk_da62921d7e3c61f9');
        $this->addSql('DROP INDEX idx_da62921d7e3c61f9');
        $this->addSql('ALTER TABLE bookmark DROP owner_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ADD owner_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bookmark.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT fk_da62921d7e3c61f9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_da62921d7e3c61f9 ON bookmark (owner_id)');
    }
}
