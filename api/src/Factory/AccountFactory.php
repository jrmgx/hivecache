<?php

namespace App\Factory;

use App\ActivityPub\KeysGenerator;
use App\Entity\Account;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Account>
 */
final class AccountFactory extends PersistentObjectFactory
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private readonly string $instanceHost,
        private readonly KeysGenerator $keysGenerator,
    ) {
        parent::__construct();
    }

    #[\Override]
    public static function class(): string
    {
        return Account::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        $key = $this->keysGenerator->generate();

        return [
            'username' => self::faker()->userName(),
            'uri' => self::faker()->url(),
            'instance' => $this->instanceHost,
            'publicKey' => (string) $key->getPublicKey(),
            'privateKey' => (string) $key,
        ];
    }
}
