<?php

namespace App\Tests\ActivityPub\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\UrlGenerator;
use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\UserFactory;
use App\Tests\BaseApiTestCase;

class OutboxControllerTest extends BaseApiTestCase
{
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlGenerator = $this->container->get(UrlGenerator::class);
    }

    public function testOutboxReturnsOrderedCollectionWithoutPagination(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne([
            'username' => 'testuser',
            'owner' => $user,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'testuser']),
        ]);

        BookmarkFactory::createMany(5, ['account' => $account, 'isPublic' => true]);

        $this->client->request('GET', '/ap/u/testuser/outbox');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('OrderedCollection', $content['type']);
        $this->assertArrayHasKey('totalItems', $content);
        $this->assertEquals(5, $content['totalItems']);
        $this->assertArrayHasKey('first', $content);
    }

    public function testOutboxReturns404WhenAccountHasNoOwner(): void
    {
        AccountFactory::createOne([
            'username' => 'testuser',
            'owner' => null,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'testuser']),
        ]);

        $this->client->request('GET', '/ap/u/testuser/outbox');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testOutboxWithPagination(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne([
            'username' => 'testuser',
            'owner' => $user,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'testuser']),
        ]);

        $bookmarks = BookmarkFactory::createMany(5, ['account' => $account, 'isPublic' => true]);

        $afterId = $bookmarks[2]->id;

        $this->client->request('GET', '/ap/u/testuser/outbox', [
            'after' => $afterId,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('OrderedCollectionPage', $content['type']);
        $this->assertArrayHasKey('orderedItems', $content);
        $this->assertCount(2, $content['orderedItems']);
        $this->assertArrayHasKey('next', $content);
    }

    public function testOutboxWithAfterFirst(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne([
            'username' => 'testuser',
            'owner' => $user,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'testuser']),
        ]);

        BookmarkFactory::createMany(5, ['account' => $account, 'isPublic' => true]);

        $this->client->request('GET', '/ap/u/testuser/outbox', [
            'after' => 'first',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('OrderedCollectionPage', $content['type']);
        $this->assertArrayHasKey('orderedItems', $content);
    }

    public function testOutboxWithNoPublicBookmarks(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne([
            'username' => 'testuser',
            'owner' => $user,
            'uri' => $this->urlGenerator->generate(RouteType::Profile, RouteAction::Get, ['username' => 'testuser']),
        ]);

        BookmarkFactory::createMany(3, ['account' => $account, 'isPublic' => false]);

        $this->client->request('GET', '/ap/u/testuser/outbox');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/activity+json');

        $response = $this->client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('OrderedCollection', $content['type']);
        $this->assertEquals(0, $content['totalItems']);
    }
}
