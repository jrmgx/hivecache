<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214161706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Save to which shared inbox the bookmark has been sent for later use in deletion for example';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ADD sent_to_shared_inboxes JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP sent_to_shared_inboxes');
    }
}
