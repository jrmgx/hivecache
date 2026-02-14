<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251125162550 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bookmark (id UUID NOT NULL, owner_id UUID NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, main_image TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, outdated BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DA62921D7E3C61F9 ON bookmark (owner_id)');
        $this->addSql('COMMENT ON COLUMN bookmark.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bookmark.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE bookmark_tag (bookmark_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(bookmark_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_23CB7F4A92741D25 ON bookmark_tag (bookmark_id)');
        $this->addSql('CREATE INDEX IDX_23CB7F4ABAD26311 ON bookmark_tag (tag_id)');
        $this->addSql('COMMENT ON COLUMN bookmark_tag.bookmark_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bookmark_tag.tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE tag (id UUID NOT NULL, owner_id UUID NOT NULL, name VARCHAR(32) NOT NULL, slug VARCHAR(32) NOT NULL, is_public BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_389B7837E3C61F9 ON tag (owner_id)');
        $this->addSql('COMMENT ON COLUMN tag.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tag.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(32) NOT NULL, is_public BOOLEAN NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON "user" (username)');
        $this->addSql('COMMENT ON COLUMN "user".id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark_tag ADD CONSTRAINT FK_23CB7F4A92741D25 FOREIGN KEY (bookmark_id) REFERENCES bookmark (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark_tag ADD CONSTRAINT FK_23CB7F4ABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B7837E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D7E3C61F9');
        $this->addSql('ALTER TABLE bookmark_tag DROP CONSTRAINT FK_23CB7F4A92741D25');
        $this->addSql('ALTER TABLE bookmark_tag DROP CONSTRAINT FK_23CB7F4ABAD26311');
        $this->addSql('ALTER TABLE tag DROP CONSTRAINT FK_389B7837E3C61F9');
        $this->addSql('DROP TABLE bookmark');
        $this->addSql('DROP TABLE bookmark_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE "user"');
    }
}
