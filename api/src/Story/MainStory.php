<?php

namespace App\Story;

use App\Factory\AccountFactory;
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
        // Users
        $user = UserFactory::createOne(['username' => 'one', 'password' => 'password']);

        // Accounts
        $account = AccountFactory::createOne(['username' => 'one', 'owner' => $user]);
        $accountExtern = AccountFactory::createOne([
            'uri' => 'https://activitypub.academy/users/braulus_aelamun',
            'username' => 'Digutus Durranoth',
            'instance' => 'activitypub.academy',
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
}
