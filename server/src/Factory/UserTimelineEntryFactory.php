<?php

namespace App\Factory;

use App\Entity\UserTimelineEntry;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserTimelineEntry>
 */
final class UserTimelineEntryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return UserTimelineEntry::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'bookmark' => BookmarkFactory::new(),
            'owner' => UserFactory::new(),
        ];
    }
}
