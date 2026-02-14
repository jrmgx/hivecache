<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251227100323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove PDF from Bookmark';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT fk_da62921d511fc912');
        $this->addSql('DROP INDEX idx_da62921d511fc912');
        $this->addSql('ALTER TABLE bookmark DROP pdf_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ADD pdf_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN bookmark.pdf_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT fk_da62921d511fc912 FOREIGN KEY (pdf_id) REFERENCES file_object (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_da62921d511fc912 ON bookmark (pdf_id)');
    }
}
