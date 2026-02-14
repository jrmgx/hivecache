<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Api\Helper\UrlHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251224092933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark ADD normalized_url TEXT');

        $bookmarks = $this->connection->fetchAllAssociative('SELECT id, url FROM bookmark');
        foreach ($bookmarks as $bookmark) {
            $normalizedUrl = UrlHelper::normalize($bookmark['url']);
            $this->addSql(
                'UPDATE bookmark SET normalized_url = :normalized_url WHERE id = :id', [
                    'normalized_url' => $normalizedUrl,
                    'id' => $bookmark['id'],
                ], [
                    'normalized_url' => \Doctrine\DBAL\ParameterType::STRING,
                    'id' => \Doctrine\DBAL\ParameterType::STRING,
                ]
            );
        }

        $this->addSql('ALTER TABLE bookmark ALTER COLUMN normalized_url SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP normalized_url');
    }
}
