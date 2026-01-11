<?php

namespace App\Story;

use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        $user = UserFactory::createOne(['username' => 'one']);
        $account = AccountFactory::createOne(['username' => 'one', 'owner' => $user]);
        TagFactory::createMany(10, ['owner' => $user]);
        $tagPublic = TagFactory::createOne(['name' => 'Tag Public', 'owner' => $user, 'isPublic' => true]);
        $tagPrivate = TagFactory::createOne(['name' => 'Tag Private', 'owner' => $user, 'isPublic' => false]);
        BookmarkFactory::createMany(10, ['account' => $account, 'isPublic' => true, 'tags' => new ArrayCollection([$tagPublic, $tagPrivate])]);
        BookmarkFactory::createMany(10, ['account' => $account, 'isPublic' => false, 'tags' => new ArrayCollection([$tagPublic, $tagPrivate])]);
    }
}
