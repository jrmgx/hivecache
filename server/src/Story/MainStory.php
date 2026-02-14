<?php

namespace App\Story;

use App\Factory\AccountFactory;
use App\Factory\AdminFactory;
use App\Factory\BookmarkFactory;
use App\Factory\InstanceTagFactory;
use App\Factory\UserFactory;
use App\Factory\UserTagFactory;
use App\Factory\UserTimelineEntryFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class MainStory extends Story
{
    public function build(): void
    {
        // Admins
        AdminFactory::createOne([
            'email' => 'admin@hivecache.test',
            'password' => 'password',
            'roles' => ['ROLE_ADMIN'],
        ]);

        // Users
        $user = UserFactory::createOne(['username' => 'one', 'password' => 'password']);

        // Accounts
        $account = AccountFactory::createOneWithUsernameAndInstance($user->username, AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
            'publicKey' => self::getOneKeys()[0],
            'privateKey' => self::getOneKeys()[1],
        ]);
        $accountExtern = AccountFactory::createOne([
            'username' => 'Digutus Durranoth',
            'instance' => 'activitypub.academy',
            'uri' => 'https://activitypub.academy/users/braulus_aelamun',
            'inboxUrl' => 'https://activitypub.academy/users/braulus_aelamun/inbox',
            'outboxUrl' => 'https://activitypub.academy/users/braulus_aelamun/outbox',
            'sharedInboxUrl' => 'https://activitypub.academy/inbox',
            'followerUrl' => 'https://activitypub.academy/users/braulus_aelamun/followers',
            'followingUrl' => 'https://activitypub.academy/users/braulus_aelamun/following',
        ]);

        // Tags
        $instanceTagPublic = InstanceTagFactory::createOne(['name' => 'Tag Public']);
        $instanceTagExtern = InstanceTagFactory::createOne(['name' => 'Tag Extern']);
        $instanceTagPrivate = InstanceTagFactory::createOne(['name' => 'Tag Private']);
        $userTagPublic = UserTagFactory::createOne([
            'name' => 'Tag Public',
            'owner' => $user,
            'isPublic' => true,
        ]);
        $userTagPrivate = UserTagFactory::createOne([
            'name' => 'Tag Private',
            'owner' => $user,
            'isPublic' => false,
        ]);

        // Bookmarks
        BookmarkFactory::createMany(10, [
            'account' => $account,
            'isPublic' => true,
            'userTags' => new ArrayCollection([$userTagPublic, $userTagPrivate]),
            'instanceTags' => new ArrayCollection([$instanceTagPublic, $instanceTagPrivate]),
        ]);
        BookmarkFactory::createMany(10, [
            'account' => $account,
            'isPublic' => false,
            'userTags' => new ArrayCollection([$userTagPublic, $userTagPrivate]),
            'instanceTags' => new ArrayCollection([$instanceTagPublic, $instanceTagPrivate]),
        ]);
        $bookmarkExtern = BookmarkFactory::createOne([
            'account' => $accountExtern,
            'isPublic' => true,
            'instanceTags' => new ArrayCollection([$instanceTagExtern]),
        ]);

        // Timeline
        UserTimelineEntryFactory::createOne([
            'bookmark' => $bookmarkExtern,
            'owner' => $user,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function getOneKeys(): array
    {
        return [
            '-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAK/aSZTdsZeRaYvvGzRkxWH0OuWyMDrE
vBoJTzOzkbXwC8x05fBT6elYGZjgJ+PVekGOCXyBwwhlEMhpxYUsR6ECAwEAAQ==
-----END PUBLIC KEY-----', '-----BEGIN PRIVATE KEY-----
MIIBVQIBADANBgkqhkiG9w0BAQEFAASCAT8wggE7AgEAAkEAr9pJlN2xl5Fpi+8b
NGTFYfQ65bIwOsS8GglPM7ORtfALzHTl8FPp6VgZmOAn49V6QY4JfIHDCGUQyGnF
hSxHoQIDAQABAkEApwLn0yxh2BNQbIggDDiQhaFQtonu6EGkbA3fXLj0cBcB6iKX
9W9Tw4VjV+zg87MQ08cTWeC1/+v3OzMhYA+fqQIhANez1ZnkyVqC1MeA+gaVinZ9
7L0BBr9ju5l7PPU2TrFzAiEA0LSZyyLQv1AqohGldDq0+rQ08RrAUYQHnZan8hhs
DZsCIQClqirdfUfgSidd6oMc13F2vBQ8vTMPf2uv32Tb+A/MXQIgK4RMwQdsYUe0
7AAj8J1BGTk0BMXgLd8Ku3grYpZnCVsCIFYTeSu6elOWAJx9AiaZGMs1O1qYMZfU
MGsYDaTMAwfx
-----END PRIVATE KEY-----
',
        ];
    }
}
