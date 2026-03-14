<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313075544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note (id UUID NOT NULL, owner_id UUID NOT NULL, bookmark_id UUID NOT NULL, content TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CFBDFA147E3C61F9 ON note (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CFBDFA1492741D25 ON note (bookmark_id)');
        $this->addSql('COMMENT ON COLUMN note.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN note.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN note.bookmark_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA147E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA1492741D25 FOREIGN KEY (bookmark_id) REFERENCES bookmark (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note DROP CONSTRAINT FK_CFBDFA147E3C61F9');
        $this->addSql('ALTER TABLE note DROP CONSTRAINT FK_CFBDFA1492741D25');
        $this->addSql('DROP TABLE note');
    }
}
