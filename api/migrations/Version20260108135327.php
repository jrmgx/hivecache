<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\ActivityPub\KeysGenerator;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260108135327 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add accounts and link to existing users';
    }

    public function up(Schema $schema): void
    {
        // Execute schema changes first
        $this->connection->executeStatement('CREATE TABLE account (id UUID NOT NULL, owner_id UUID DEFAULT NULL, public_key TEXT DEFAULT NULL, private_key TEXT DEFAULT NULL, uri TEXT NOT NULL, username VARCHAR(255) NOT NULL, last_updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->connection->executeStatement('CREATE UNIQUE INDEX UNIQ_7D3656A47E3C61F9 ON account (owner_id)');
        $this->connection->executeStatement('COMMENT ON COLUMN account.id IS \'(DC2Type:uuid)\'');
        $this->connection->executeStatement('COMMENT ON COLUMN account.owner_id IS \'(DC2Type:uuid)\'');
        $this->connection->executeStatement('COMMENT ON COLUMN account.last_updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->connection->executeStatement('ALTER TABLE account ADD CONSTRAINT FK_7D3656A47E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->connection->executeStatement('ALTER TABLE bookmark ADD account_id UUID DEFAULT NULL');
        $this->connection->executeStatement('ALTER TABLE bookmark ADD from_this_server BOOLEAN DEFAULT NULL');
        $this->connection->executeStatement('UPDATE bookmark SET from_this_server = true WHERE from_this_server IS NULL');
        $this->connection->executeStatement('ALTER TABLE bookmark ALTER COLUMN from_this_server SET NOT NULL');
        $this->connection->executeStatement('ALTER TABLE bookmark ALTER owner_id DROP NOT NULL');
        $this->connection->executeStatement('COMMENT ON COLUMN bookmark.account_id IS \'(DC2Type:uuid)\'');
        $this->connection->executeStatement('ALTER TABLE bookmark ADD CONSTRAINT FK_DA62921D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->connection->executeStatement('CREATE INDEX IDX_DA62921D9B6B5FBA ON bookmark (account_id)');
        $this->connection->executeStatement('CREATE INDEX from_this_server_idx ON bookmark (from_this_server)');

        // Now create accounts for existing users
        $users = $this->connection->fetchAllAssociative('SELECT id, username FROM "user"');

        foreach ($users as $user) {
            $accountId = Uuid::v7()->toString();
            $keyPair = KeysGenerator::doctrineMigrationHelper();
            $publicKey = $keyPair['public'];
            $privateKey = $keyPair['private'];
            $uri = "https://api.hivecache.test/profile/{$user['username']}";
            $lastUpdatedAt = new \DateTimeImmutable()->format('Y-m-d H:i:s');

            $this->connection->executeStatement(
                'INSERT INTO account (id, owner_id, public_key, private_key, uri, username, last_updated_at) VALUES (:id, :owner_id, :public_key, :private_key, :uri, :username, :last_updated_at)',
                [
                    'id' => $accountId,
                    'owner_id' => $user['id'],
                    'public_key' => $publicKey,
                    'private_key' => $privateKey,
                    'uri' => $uri,
                    'username' => $user['username'],
                    'last_updated_at' => $lastUpdatedAt,
                ],
                [
                    'id' => ParameterType::STRING,
                    'owner_id' => ParameterType::STRING,
                    'public_key' => ParameterType::STRING,
                    'private_key' => ParameterType::STRING,
                    'uri' => ParameterType::STRING,
                    'username' => ParameterType::STRING,
                    'last_updated_at' => ParameterType::STRING,
                ]
            );
        }

        $this->connection->executeStatement(
            'UPDATE bookmark SET account_id = account.id FROM account WHERE bookmark.owner_id = account.owner_id AND bookmark.owner_id IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bookmark DROP CONSTRAINT FK_DA62921D9B6B5FBA');
        $this->addSql('ALTER TABLE account DROP CONSTRAINT FK_7D3656A47E3C61F9');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP INDEX IDX_DA62921D9B6B5FBA');
        $this->addSql('DROP INDEX from_this_server_idx');
        $this->addSql('ALTER TABLE bookmark DROP account_id');
        $this->addSql('ALTER TABLE bookmark DROP from_this_server');
        $this->addSql('ALTER TABLE bookmark ALTER owner_id SET NOT NULL');
    }
}
