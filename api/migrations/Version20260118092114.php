<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118092114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FileObject owner can be null';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark_user_tag DROP CONSTRAINT fk_59f9e747df80782c');
        $this->addSql('ALTER TABLE file_object ALTER owner_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file_object ALTER owner_id SET NOT NULL');
    }
}
