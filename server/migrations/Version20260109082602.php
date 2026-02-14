<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260109082602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update Bookmark';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX from_this_server_idx');
        $this->addSql('ALTER TABLE bookmark ADD instance VARCHAR(255) DEFAULT \'https://hivecache.test\' NOT NULL');
        $this->addSql('ALTER TABLE bookmark DROP from_this_server');
        $this->addSql('CREATE INDEX instance_idx ON bookmark (instance)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX instance_idx');
        $this->addSql('ALTER TABLE bookmark ADD from_this_server BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE bookmark DROP instance');
        $this->addSql('CREATE INDEX from_this_server_idx ON bookmark (from_this_server)');
    }
}
