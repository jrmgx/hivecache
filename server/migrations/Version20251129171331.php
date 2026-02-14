<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129171331 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookmark ADD pdf_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bookmark.pdf_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D511FC912 FOREIGN KEY (pdf_id) REFERENCES file_object (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DA62921D511FC912 ON bookmark (pdf_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D511FC912');
        $this->addSql('DROP INDEX IDX_DA62921D511FC912');
        $this->addSql('ALTER TABLE bookmark DROP pdf_id');
    }
}
