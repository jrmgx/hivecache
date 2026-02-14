<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260117093728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Re organize tags part 2';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bookmark_instance_tag (bookmark_id UUID NOT NULL, instance_tag_id UUID NOT NULL, PRIMARY KEY(bookmark_id, instance_tag_id))');
        $this->addSql('CREATE INDEX IDX_627E6A4C92741D25 ON bookmark_instance_tag (bookmark_id)');
        $this->addSql('CREATE INDEX IDX_627E6A4CF9B7146A ON bookmark_instance_tag (instance_tag_id)');
        $this->addSql('COMMENT ON COLUMN bookmark_instance_tag.bookmark_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bookmark_instance_tag.instance_tag_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE instance_tag (id UUID NOT NULL, name VARCHAR(32) NOT NULL, slug VARCHAR(32) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX unique_slug ON instance_tag (slug)');
        $this->addSql('COMMENT ON COLUMN instance_tag.id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE bookmark_instance_tag ADD CONSTRAINT FK_627E6A4C92741D25 FOREIGN KEY (bookmark_id) REFERENCES bookmark (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark_instance_tag ADD CONSTRAINT FK_627E6A4CF9B7146A FOREIGN KEY (instance_tag_id) REFERENCES instance_tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        // Copy user tags to instance tags (one per unique slug)
        $this->addSql('INSERT INTO instance_tag (id, name, slug) SELECT DISTINCT ON (slug) id, name, slug FROM user_tag ORDER BY slug, name');
        // Associate bookmarks with instance tags based on user tag relationships (matching by slug)
        $this->addSql('INSERT INTO bookmark_instance_tag (bookmark_id, instance_tag_id) SELECT DISTINCT but.bookmark_id, it.id FROM bookmark_user_tag but JOIN user_tag ut ON but.tag_id = ut.id JOIN instance_tag it ON ut.slug = it.slug ON CONFLICT DO NOTHING');
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT IF EXISTS FK_23CB7F4ABAD26311');
        $this->addSql('DROP INDEX IF EXISTS idx_23cb7f4abad26311');
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT IF EXISTS bookmark_tag_pkey');
        $this->addSql('ALTER TABLE bookmark_user_tag RENAME COLUMN tag_id TO user_tag_id');
        $this->addSql('ALTER TABLE bookmark_user_tag ADD CONSTRAINT FK_59F9E747DF80782C FOREIGN KEY (user_tag_id) REFERENCES user_tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_59F9E747DF80782C ON bookmark_user_tag (user_tag_id)');
        $this->addSql('ALTER TABLE bookmark_user_tag ADD PRIMARY KEY (bookmark_id, user_tag_id)');
        $this->addSql('ALTER INDEX idx_23cb7f4a92741d25 RENAME TO IDX_59F9E74792741D25');
        $this->addSql('ALTER INDEX unique_owner_slug_user_tag RENAME TO unique_owner_slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark_instance_tag DROP CONSTRAINT FK_627E6A4C92741D25');
        $this->addSql('ALTER TABLE bookmark_instance_tag DROP CONSTRAINT FK_627E6A4CF9B7146A');
        $this->addSql('DROP TABLE bookmark_instance_tag');
        $this->addSql('DROP TABLE instance_tag');
        $this->addSql('ALTER INDEX unique_owner_slug RENAME TO unique_owner_slug_user_tag');
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT FK_59F9E747DF80782C');
        $this->addSql('DROP INDEX IDX_59F9E747DF80782C');
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT bookmark_user_tag_pkey');
        $this->addSql('ALTER TABLE bookmark_user_tag RENAME COLUMN user_tag_id TO tag_id');
        $this->addSql('ALTER TABLE bookmark_user_tag ADD CONSTRAINT FK_23CB7F4ABAD26311 FOREIGN KEY (tag_id) REFERENCES user_tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_23cb7f4abad26311 ON bookmark_user_tag (tag_id)');
        $this->addSql('ALTER TABLE bookmark_user_tag ADD PRIMARY KEY (bookmark_id, tag_id)');
        $this->addSql('ALTER INDEX idx_59f9e74792741d25 RENAME TO idx_23cb7f4a92741d25');
    }
}
