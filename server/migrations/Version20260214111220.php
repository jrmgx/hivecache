<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214111220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Account URI must be unique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656A4841CB121 ON account (uri)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_7D3656A4841CB121');
    }
}
