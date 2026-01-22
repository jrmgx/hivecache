<?php

namespace App\Factory;

use App\Entity\Following;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Following>
 */
final class FollowingFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Following::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'owner' => UserFactory::new(),
            'account' => AccountFactory::new(),
        ];
    }
}
