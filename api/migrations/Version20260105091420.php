<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260105091420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bookmark Index related';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bookmark_index_action (id UUID NOT NULL, owner_id UUID NOT NULL, type VARCHAR(255) NOT NULL, bookmark_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_374DFB947E3C61F9 ON bookmark_index_action (owner_id)');
        $this->addSql('COMMENT ON COLUMN bookmark_index_action.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bookmark_index_action.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bookmark_index_action.bookmark_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark_index_action ADD CONSTRAINT FK_374DFB947E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark_index_action DROP CONSTRAINT FK_374DFB947E3C61F9');
        $this->addSql('DROP TABLE bookmark_index_action');
    }
}
