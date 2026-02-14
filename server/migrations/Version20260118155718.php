<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118155718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UserTimeLine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_timeline_entry (id UUID NOT NULL, owner_id UUID NOT NULL, bookmark_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_60CACF6F7E3C61F9 ON user_timeline_entry (owner_id)');
        $this->addSql('CREATE INDEX IDX_60CACF6F92741D25 ON user_timeline_entry (bookmark_id)');
        $this->addSql('COMMENT ON COLUMN user_timeline_entry.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_timeline_entry.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN user_timeline_entry.bookmark_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_timeline_entry ADD CONSTRAINT FK_60CACF6F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_timeline_entry ADD CONSTRAINT FK_60CACF6F92741D25 FOREIGN KEY (bookmark_id) REFERENCES bookmark (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE bookmark_user_tag ADD CONSTRAINT FK_59F9E747DF80782C FOREIGN KEY (user_tag_id) REFERENCES user_tag (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_timeline_entry DROP CONSTRAINT FK_60CACF6F7E3C61F9');
        $this->addSql('ALTER TABLE user_timeline_entry DROP CONSTRAINT FK_60CACF6F92741D25');
        $this->addSql('DROP TABLE user_timeline_entry');
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT FK_59F9E747DF80782C');
    }
}
