<?php

namespace App\Factory;

use App\Entity\Tag;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Tag>
 */
final class TagFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return Tag::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        /** @var string $name */
        $name = self::faker()->words(2, asText: true);

        return [
            'name' => ucfirst($name),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
        // ->afterInstantiate(function(Tag $bookmark): void {})
    }
}
