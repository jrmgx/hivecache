<?php

namespace App\Tests\ActivityPub\Bundler;

use App\ActivityPub\Bundler\FollowActivityBundler;
use App\Factory\AccountFactory;
use App\Factory\FollowingFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FollowActivityBundlerTest extends KernelTestCase
{
    public function testBundleFromFollowing(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var FollowActivityBundler $followActivityBundler */
        $followActivityBundler = $container->get(FollowActivityBundler::class);

        $user = UserFactory::createOne(['username' => 'testuser']);
        $actorAccount = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);
        $objectAccount = AccountFactory::createOne(['username' => 'followeduser']);

        $following = FollowingFactory::createOne([
            'owner' => $user,
            'account' => $objectAccount,
        ]);

        $followActivity = $followActivityBundler->bundleFromFollowing($following);

        $this->assertEquals('Follow', $followActivity->type);
        $this->assertEquals($actorAccount->uri, $followActivity->actor);
        $this->assertEquals($objectAccount->uri, $followActivity->object);
        $this->assertStringContainsString($following->id, $followActivity->id);
    }
}
