<?php

namespace App\Story;

use App\Factory\AccountFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'ap_server')]
final class ApServerStory extends Story
{
    public function build(): void
    {
        // Accounts
        AccountFactory::createOne([ // internaluser@hivecache.test
            'uri' => 'http://hivecache.test/users/internaluser',
            'username' => 'internaluser',
            'instance' => 'hivecache.test',
            'inboxUrl' => 'http://hivecache.test/ap/u/internaluser/inbox',
            'outboxUrl' => 'http://hivecache.test/ap/u/internaluser/outbox',
            'sharedInboxUrl' => 'http://hivecache.test/ap/inbox',
            'followerUrl' => 'http://hivecache.test/ap/u/internaluser/follower',
            'followingUrl' => 'http://hivecache.test/ap/u/internaluser/following',
        ]);
    }
}
