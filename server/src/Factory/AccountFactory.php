<?php

namespace App\Factory;

use App\ActivityPub\KeysGenerator;
use App\Entity\Account;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Account>
 */
final class AccountFactory extends PersistentObjectFactory
{
    public const string TEST_AP_SERVER_INSTANCE = 'external_ap_server.test:8000';
    public const string TEST_INSTANCE = 'hivecache.test';

    public function __construct(
        private readonly KeysGenerator $keysGenerator,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return Account::class;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function createOneWithUsernameAndInstance(
        string $username,
        string $instance = self::TEST_INSTANCE,
        array $attributes = [],
    ): Account {
        return self::createOne([...$attributes, ...[
            'username' => $username,
            'instance' => $instance,
            'uri' => "https://{$instance}/profile/{$username}",
            'inboxUrl' => "https://{$instance}/ap/u/{$username}/inbox",
            'outboxUrl' => "https://{$instance}/ap/u/{$username}/outbox",
            'sharedInboxUrl' => "https://{$instance}/ap/inbox",
            'followerUrl' => "https://{$instance}/ap/u/{$username}/follower",
            'followingUrl' => "https://{$instance}/ap/u/{$username}/following",
        ]]);
    }

    protected function defaults(): array|callable
    {
        $key = $this->keysGenerator->generate();
        $username = self::faker()->userName();
        $instance = self::TEST_AP_SERVER_INSTANCE;

        return [
            'username' => $username,
            'publicKey' => $key['public'],
            'privateKey' => $key['private'],
            'instance' => $instance,
            'uri' => "https://{$instance}/profile/{$username}",
            'inboxUrl' => "https://{$instance}/ap/u/{$username}/inbox",
            'outboxUrl' => "https://{$instance}/ap/u/{$username}/outbox",
            'sharedInboxUrl' => "https://{$instance}/ap/inbox",
            'followerUrl' => "https://{$instance}/ap/u/{$username}/follower",
            'followingUrl' => "https://{$instance}/ap/u/{$username}/following",
        ];
    }
}
