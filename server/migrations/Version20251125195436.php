<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125195436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file_object ADD owner_id UUID NOT NULL');
        $this->addSql('COMMENT ON COLUMN file_object.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE file_object ADD CONSTRAINT FK_10BA8D537E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_10BA8D537E3C61F9 ON file_object (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE file_object DROP CONSTRAINT FK_10BA8D537E3C61F9');
        $this->addSql('DROP INDEX IDX_10BA8D537E3C61F9');
        $this->addSql('ALTER TABLE file_object DROP owner_id');
    }
}
