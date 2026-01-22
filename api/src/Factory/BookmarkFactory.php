<?php

namespace App\Factory;

use App\Entity\Bookmark;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Bookmark>
 */
final class BookmarkFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     */
    public function __construct(
        #[Autowire('%instanceHost%')]
        private readonly string $instanceHost,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return Bookmark::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->words(asText: true),
            'url' => self::faker()->url(),
            'account' => AccountFactory::new(),
            'instance' => $this->instanceHost,
        ];
    }
}
