<?php

namespace App\Tests\Api\Controller;

use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\InstanceTagFactory;
use App\Factory\UserTimelineEntryFactory;
use App\Tests\BaseApiTestCase;
use Doctrine\Common\Collections\ArrayCollection;

class MeBookmarkSocialControllerTest extends BaseApiTestCase
{
    public function testGetTimelineReturnsBookmarks(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $account = AccountFactory::createOneWithUsernameAndInstance('otheruser');
        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'isPublic' => true,
        ]);
        UserTimelineEntryFactory::createOne([
            'bookmark' => $bookmark,
            'owner' => $user,
        ]);

        $this->assertUnauthorized('GET', '/users/me/bookmarks/social/timeline');

        $this->request('GET', '/users/me/bookmarks/social/timeline', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);
        $this->assertGreaterThanOrEqual(1, \count($json['collection']), 'Timeline should have at least one bookmark');
        $this->assertBookmarkCollection($json['collection'], private: false);
    }

    public function testGetTagBookmarksReturnsBookmarks(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $account = AccountFactory::createOneWithUsernameAndInstance('otheruser');
        $tag = InstanceTagFactory::createOne(['name' => 'Tag Extern Social']);
        BookmarkFactory::createOne([
            'account' => $account,
            'isPublic' => true,
            'instanceTags' => new ArrayCollection([$tag]),
        ]);

        $this->assertUnauthorized('GET', '/users/me/bookmarks/social/tag/' . $tag->slug);

        $this->client->enableProfiler();
        $this->request('GET', '/users/me/bookmarks/social/tag/' . $tag->slug, ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);
        $this->assertGreaterThanOrEqual(1, \count($json['collection']), 'Tag bookmarks should have at least one bookmark');
        $this->assertBookmarkCollection($json['collection'], false); // false because it's public bookmarks
    }
}
