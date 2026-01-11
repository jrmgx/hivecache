<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110085750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Follow related';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE follower (id UUID NOT NULL, account_id UUID NOT NULL, owner_id UUID NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B9D609469B6B5FBA ON follower (account_id)');
        $this->addSql('CREATE INDEX IDX_B9D609467E3C61F9 ON follower (owner_id)');
        $this->addSql('COMMENT ON COLUMN follower.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN follower.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN follower.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE following (id UUID NOT NULL, owner_id UUID NOT NULL, account_id UUID NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_71BF8DE37E3C61F9 ON following (owner_id)');
        $this->addSql('CREATE INDEX IDX_71BF8DE39B6B5FBA ON following (account_id)');
        $this->addSql('COMMENT ON COLUMN following.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN following.owner_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN following.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE follower ADD CONSTRAINT FK_B9D609469B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE follower ADD CONSTRAINT FK_B9D609467E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE following ADD CONSTRAINT FK_71BF8DE37E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE following ADD CONSTRAINT FK_71BF8DE39B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE follower DROP CONSTRAINT FK_B9D609469B6B5FBA');
        $this->addSql('ALTER TABLE follower DROP CONSTRAINT FK_B9D609467E3C61F9');
        $this->addSql('ALTER TABLE following DROP CONSTRAINT FK_71BF8DE37E3C61F9');
        $this->addSql('ALTER TABLE following DROP CONSTRAINT FK_71BF8DE39B6B5FBA');
        $this->addSql('DROP TABLE follower');
        $this->addSql('DROP TABLE following');
    }
}
