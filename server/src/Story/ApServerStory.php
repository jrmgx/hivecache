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
        AccountFactory::createOneWithUsernameAndInstance('one', AccountFactory::TEST_INSTANCE, [
            'publicKey' => MainStory::getOneKeys()[0],
        ]);
    }
}
