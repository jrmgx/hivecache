<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260109082613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update Bookmark';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ALTER instance DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ALTER instance SET DEFAULT \'https://hivecache.test\'');
    }
}
