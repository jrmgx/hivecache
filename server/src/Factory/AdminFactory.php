<?php

namespace App\Factory;

use App\Entity\Admin;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Admin>
 */
final class AdminFactory extends PersistentObjectFactory
{
    // The injected service should be nullable in order to be used in unit test, without container
    public function __construct(
        private readonly ?UserPasswordHasherInterface $passwordHasher = null,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return Admin::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'email' => self::faker()->email(),
            'password' => 'password',
        ];
    }

    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (Admin $admin) {
                if (null !== $this->passwordHasher) {
                    $admin->setPassword($this->passwordHasher->hashPassword($admin, $admin->getPassword()));
                }
            })
        ;
    }
}
