<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251128074910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookmark ADD main_image_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE bookmark DROP main_image');
        $this->addSql('COMMENT ON COLUMN bookmark.main_image_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921DE4873418 FOREIGN KEY (main_image_id) REFERENCES file_object (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_DA62921DE4873418 ON bookmark (main_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921DE4873418');
        $this->addSql('DROP INDEX IDX_DA62921DE4873418');
        $this->addSql('ALTER TABLE bookmark ADD main_image TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE bookmark DROP main_image_id');
    }
}
