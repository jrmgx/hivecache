<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125193654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file_object (id UUID NOT NULL, file_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN file_object.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD archive_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bookmark.archive_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D2956195F FOREIGN KEY (archive_id) REFERENCES file_object (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DA62921D2956195F ON bookmark (archive_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D2956195F');
        $this->addSql('DROP TABLE file_object');
        $this->addSql('DROP INDEX IDX_DA62921D2956195F');
        $this->addSql('ALTER TABLE bookmark DROP archive_id');
    }
}
