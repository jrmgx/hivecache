<?php

namespace App\Tests\ActivityPub\Bundler;

use App\ActivityPub\Bundler\CreateActivityBundler;
use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\FollowerFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CreateActivityBundlerTest extends KernelTestCase
{
    public function testBundleFromBookmark(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var CreateActivityBundler $createActivityBundler */
        $createActivityBundler = $container->get(CreateActivityBundler::class);

        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);
        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Test Bookmark',
            'url' => 'https://example.com/test',
            'isPublic' => true,
        ]);

        $follower1 = FollowerFactory::createOne(['owner' => $user]);
        $follower2 = FollowerFactory::createOne(['owner' => $user]);
        $followers = [$follower1, $follower2];

        $createActivity = $createActivityBundler->bundleFromBookmark($bookmark, $followers);

        $this->assertEquals('Create', $createActivity->type);
        $this->assertEquals($account->uri, $createActivity->actor);
        $this->assertNotNull($createActivity->object);
        $this->assertEquals('Note', $createActivity->object->type);
        $this->assertEquals($createActivity->object->id, $createActivity->id);
        $this->assertStringContainsString($bookmark->title, $createActivity->object->content);
        $this->assertStringContainsString($bookmark->url, $createActivity->object->content);
        $this->assertCount(2, $createActivity->cc);
        $this->assertContains($follower1->account->uri, $createActivity->cc);
        $this->assertContains($follower2->account->uri, $createActivity->cc);
        $this->assertEquals($createActivity->object->published, $createActivity->published);
    }
}
