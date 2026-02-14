<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260116161359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Re organize tags part 1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag RENAME TO user_tag;');
        $this->addSql('ALTER TABLE bookmark_tag RENAME TO bookmark_user_tag;');
        // Rename indexes to match the new table name (constraints keep their names as they don't reference table names)
        $this->addSql('ALTER INDEX IDX_389B7837E3C61F9 RENAME TO IDX_E89FD6087E3C61F9');
        $this->addSql('ALTER INDEX unique_owner_slug RENAME TO unique_owner_slug_user_tag');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_tag RENAME TO tag;');
        $this->addSql('ALTER TABLE bookmark_user_tag RENAME TO bookmark_tag;');
        // Rename indexes and constraints back to original names
        $this->addSql('ALTER INDEX IDX_E89FD6087E3C61F9 RENAME TO IDX_389B7837E3C61F9');
        $this->addSql('ALTER INDEX unique_owner_slug_user_tag RENAME TO unique_owner_slug');
    }
}
