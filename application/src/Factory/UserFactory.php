<?php

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct()
    {
        parent::__construct();
    }

    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'username' => self::faker()->userName(),
            'email' => self::faker()->email(),
            'password' => 'undefined_password',
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
        // ->afterInstantiate(function(User $user): void {})
    }
}
