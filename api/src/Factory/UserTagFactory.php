<?php

namespace App\Factory;

use App\Entity\UserTag;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserTag>
 */
final class UserTagFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return UserTag::class;
    }

    protected function defaults(): array|callable
    {
        /** @var string $name */
        $name = self::faker()->words(2, asText: true);

        return [
            'name' => ucfirst($name),
            'owner' => UserFactory::new(),
        ];
    }
}
