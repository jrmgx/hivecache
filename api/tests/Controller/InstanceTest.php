<?php

namespace App\Tests\Controller;

use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\UserFactory;
use App\Factory\UserTagFactory;
use App\Tests\BaseApiTestCase;
use Doctrine\Common\Collections\ArrayCollection;

class InstanceTest extends BaseApiTestCase
{
    public function testInstanceThisEndpointFiltersPrivateTags(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $privateTag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'Private Tag',
            'isPublic' => false,
        ]);

        $publicTag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'Public Tag',
            'isPublic' => true,
        ]);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Public Bookmark With Mixed Tags',
            'url' => 'https://example.com',
            'isPublic' => true,
            'userTags' => new ArrayCollection([$privateTag, $publicTag]),
        ]);

        $this->request('GET', '/instance/this');
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);
        $this->assertCount(1, $json['collection'], 'Should find the public bookmark');

        $foundBookmark = $json['collection'][0];
        $this->assertEquals($bookmark->id, $foundBookmark['id']);
        $this->assertEquals('Public Bookmark With Mixed Tags', $foundBookmark['title']);
        $this->assertIsArray($foundBookmark['tags']);
        $this->assertCount(1, $foundBookmark['tags'], 'Should only show the public tag');
        $this->assertIsArray($foundBookmark['tags'][0]);

        $tag = $foundBookmark['tags'][0];
        $this->assertEquals('Public Tag', $tag['name'], 'Should contain the public tag');
        $this->assertEquals('public-tag', $tag['slug']);
    }
}
