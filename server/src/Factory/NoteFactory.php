<?php

namespace App\Factory;

use App\Entity\Note;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Note>
 */
final class NoteFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Note::class;
    }

    protected function defaults(): array|callable
    {
        return function () {
            $owner = UserFactory::createOne();
            $account = AccountFactory::createOneWithUsernameAndInstance(
                $owner->username,
                AccountFactory::TEST_INSTANCE,
                ['owner' => $owner]
            );
            $bookmark = BookmarkFactory::createOne([
                'account' => $account,
                'instance' => AccountFactory::TEST_INSTANCE,
                'url' => 'https://example.com/' . self::faker()->slug(),
            ]);

            return [
                'content' => self::faker()->paragraph(),
                'owner' => $owner,
                'bookmark' => $bookmark,
            ];
        };
    }
}
