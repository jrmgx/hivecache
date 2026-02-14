<?php

namespace App\Tests\ActivityPub\Bundler;

use App\ActivityPub\Bundler\DeleteActivityBundler;
use App\Factory\AccountFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeleteActivityBundlerTest extends KernelTestCase
{
    public function testBundle(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var DeleteActivityBundler $deleteActivityBundler */
        $deleteActivityBundler = $container->get(DeleteActivityBundler::class);

        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOneWithUsernameAndInstance('testuser', AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
        ]);

        $bookmarkUri = 'https://hivecache.test/users/me/bookmarks/123';
        $bookmarkUrl = 'https://example.com/article';

        $deleteActivity = $deleteActivityBundler->bundle($account->uri, $bookmarkUri, $bookmarkUrl);

        $this->assertEquals('Delete', $deleteActivity->type);
        $this->assertEquals($account->uri, $deleteActivity->actor);
        $this->assertEquals($bookmarkUri . '#delete', $deleteActivity->id);
        $this->assertNotNull($deleteActivity->object);
        $this->assertEquals('Tombstone', $deleteActivity->object->type);
        $this->assertEquals($bookmarkUri . '#' . $bookmarkUrl, $deleteActivity->object->id);
    }
}
