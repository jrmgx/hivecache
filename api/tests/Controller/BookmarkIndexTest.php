<?php

namespace App\Tests\Controller;

use App\Entity\Bookmark;
use App\Entity\BookmarkIndexAction;
use App\Enum\BookmarkIndexActionType;
use App\Factory\BookmarkFactory;
use App\Repository\BookmarkIndexActionRepository;
use App\Repository\BookmarkRepository;
use App\Tests\BaseApiTestCase;

class BookmarkIndexTest extends BaseApiTestCase
{
    private BookmarkIndexActionRepository $bookmarkIndexActionRepository;
    private BookmarkRepository $bookmarkRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bookmarkRepository = $this->entityManager->getRepository(Bookmark::class);
        $this->bookmarkIndexActionRepository = $this->entityManager->getRepository(BookmarkIndexAction::class);
    }

    public function testCreateBookmarkCreatesIndexAction(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();
        $bookmarkId = $json['id'];

        $bookmark = $this->bookmarkRepository->find($bookmarkId);
        $this->assertNotNull($bookmark, 'Bookmark should exist');

        // Get all index actions for this bookmark and verify the latest one is Create
        $allActions = $this->bookmarkIndexActionRepository->findBy(['bookmarkId' => $bookmark->id], ['id' => 'DESC']);
        $this->assertNotEmpty($allActions, 'At least one BookmarkIndexAction should exist');

        $latestAction = $allActions[0];
        $this->assertEquals(BookmarkIndexActionType::Created, $latestAction->type, 'Latest BookmarkIndexAction should be Create type');
        $this->assertEquals($bookmarkId, $latestAction->bookmarkId);
        $this->assertEquals($user->id, $latestAction->owner->id);
    }

    public function testUpdateBookmarkCreatesIndexAction(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Original Title',
                'url' => 'https://example.com',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();
        $bookmarkId = $json['id'];

        $bookmark = $this->bookmarkRepository->find($bookmarkId);
        $this->assertNotNull($bookmark, 'Bookmark should exist');

        $this->request('PATCH', "/users/me/bookmarks/{$bookmarkId}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Updated Title',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Get all index actions for this bookmark and verify the latest one is Update
        $allActions = $this->bookmarkIndexActionRepository->findBy(['bookmarkId' => $bookmark->id], ['id' => 'DESC']);
        $this->assertNotEmpty($allActions, 'At least one BookmarkIndexAction should exist');

        $latestAction = $allActions[0];
        $this->assertEquals(BookmarkIndexActionType::Updated, $latestAction->type, 'Latest BookmarkIndexAction should be Update type');
        $this->assertEquals($bookmarkId, $latestAction->bookmarkId);
        $this->assertEquals($user->id, $latestAction->owner->id);
    }

    public function testOutdateBookmarkCreatesIndexAction(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $url = 'https://example.com/test-bookmark';

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 1',
                'url' => $url,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $bookmark1Json = $this->getResponseArray();
        $bookmark1Id = $bookmark1Json['id'];

        $bookmark1 = $this->bookmarkRepository->find($bookmark1Id);
        $this->assertNotNull($bookmark1, 'First bookmark should exist');

        // Create second bookmark with same URL (this will outdate the first one)
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 2',
                'url' => $url,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Get all index actions for the first bookmark and verify the latest one is Outdated
        $allActions = $this->bookmarkIndexActionRepository->findBy(['bookmarkId' => $bookmark1->id], ['id' => 'DESC']);
        $this->assertNotEmpty($allActions, 'At least one BookmarkIndexAction should exist');

        $latestAction = $allActions[0];
        $this->assertEquals(BookmarkIndexActionType::Outdated, $latestAction->type, 'Latest BookmarkIndexAction should be Outdated type');
        $this->assertEquals($bookmark1Id, $latestAction->bookmarkId);
        $this->assertEquals($user->id, $latestAction->owner->id);
    }

    public function testDeleteBookmarkCreatesIndexAction(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Bookmark To Delete',
                'url' => 'https://example.com',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();
        $bookmarkId = $json['id'];

        $bookmark = $this->bookmarkRepository->find($bookmarkId);
        $this->assertNotNull($bookmark, 'Bookmark should exist');

        $this->request('DELETE', "/users/me/bookmarks/{$bookmarkId}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        $allActions = $this->bookmarkIndexActionRepository->findBy(['bookmarkId' => $bookmarkId], ['id' => 'DESC']);
        $this->assertNotEmpty($allActions, 'At least one BookmarkIndexAction should exist');

        $latestAction = $allActions[0];
        $this->assertEquals(BookmarkIndexActionType::Deleted, $latestAction->type, 'Latest BookmarkIndexAction should be Delete type');
        $this->assertEquals($bookmarkId, $latestAction->bookmarkId);
        $this->assertEquals($user->id, $latestAction->owner->id);
    }

    public function testGetSearchIndexReturnsBookmarks(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Create some bookmarks for the user
        BookmarkFactory::createMany(3, ['account' => $account]);

        $this->assertUnauthorized('GET', '/users/me/bookmarks/search/index');

        $this->request('GET', '/users/me/bookmarks/search/index', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);
        $this->assertCount(3, $json['collection']);
        $this->assertBookmarkCollection($json['collection']);
    }

    public function testGetSearchDiffReturnsIndexActions(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmarks = BookmarkFactory::createMany(5, ['account' => $account]);

        $this->request('PATCH', "/users/me/bookmarks/{$bookmarks[0]->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['title' => 'Updated Title 1'],
        ]);
        $this->assertResponseIsSuccessful();

        $this->request('PATCH', "/users/me/bookmarks/{$bookmarks[1]->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['title' => 'Updated Title 2'],
        ]);
        $this->assertResponseIsSuccessful();

        $this->request('GET', '/users/me/bookmarks/search/diff', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);

        $updatedActions = array_filter($json['collection'], fn ($action) => 'updated' === $action['type']);
        $this->assertCount(2, $updatedActions);

        $lastActionId = $json['collection'][\count($json['collection']) - 1]['id'];

        $this->request('DELETE', "/users/me/bookmarks/{$bookmarks[2]->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('PATCH', "/users/me/bookmarks/{$bookmarks[3]->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['title' => 'Updated Title 3'],
        ]);
        $this->assertResponseIsSuccessful();

        $this->request('GET', "/users/me/bookmarks/search/diff?before={$lastActionId}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('collection', $json);
        $this->assertIsArray($json['collection']);
        $this->assertCount(2, $json['collection']);

        $actionTypes = array_column($json['collection'], 'type');
        $this->assertContains('deleted', $actionTypes);
        $this->assertContains('updated', $actionTypes);
    }
}
