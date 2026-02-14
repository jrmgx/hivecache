<?php

namespace App\Factory;

use App\Entity\Follower;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Follower>
 */
final class FollowerFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Follower::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'owner' => UserFactory::new(),
            'account' => AccountFactory::new(),
        ];
    }
}
