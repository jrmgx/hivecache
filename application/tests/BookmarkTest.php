<?php

namespace App\Tests;

use App\Factory\BookmarkFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;

class BookmarkTest extends BaseApiTestCase
{
    public function testListOwnBookmarks(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        BookmarkFactory::createMany(3, ['owner' => $user, 'tags' => new ArrayCollection([$tag1, $tag2])]);
        BookmarkFactory::createMany(2, ['owner' => $user, 'tags' => new ArrayCollection([$tag1])]);

        $this->assertUnauthorized('GET', '/api/users/me/bookmarks');

        $response = $this->client->request('GET', '/api/users/me/bookmarks', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(5, $json['member']);
        $this->assertBookmarkOwnerCollection($json['member']);
    }

    public function testListOwnBookmarksWithFilter(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag One', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Two', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Three', 'isPublic' => true]);
        $tagPrivate = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Two, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark Without Tag One',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag3, $tagPrivate]),
        ]);

        $response = $this->client->request('GET', '/api/users/me/bookmarks?tags=Tag%20One,Tag%20Two', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(3, $json['member']);
        $this->assertEquals('Bookmark With Tag One, Two, Three, Private', $json['member'][0]['title']);
        $this->assertEquals('https://public.com', $json['member'][0]['url']);
        $this->assertIsArray($json['member'][0]['tags']);
        $this->assertCount(4, $json['member'][0]['tags']); // All tags
        $this->assertBookmarkOwnerCollection($json['member']);

        $response = $this->client->request('GET', '/api/users/me/bookmarks?tags=Private%20Tag', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(3, $json['member']);
    }

    public function testCreateBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        $this->assertUnauthorized('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
            ],
        ]);

        $response = $this->client->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
                'tags' => [
                    "/api/users/me/tags/{$tag1->slug}",
                    "/api/users/me/tags/{$tag2->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertEquals('Test Bookmark', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);
    }

    public function testCreateBookmarkWithFile(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile(__DIR__ . '/data/image_01.jpg', 'image_01.jpg');

        $fileResponse = $this->client->request('POST', '/api/file_objects', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'auth_bearer' => $token,
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $fileJson = $fileResponse->toArray();
        $fileObjectIri = $fileJson['@id'];

        $this->assertUnauthorized('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'title' => 'Test Bookmark with Archive',
                'url' => 'https://example.com',
                'archive' => $fileObjectIri,
            ],
        ]);

        $response = $this->client->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Test Bookmark with Archive',
                'url' => 'https://example.com',
                'archive' => $fileObjectIri,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertEquals('Test Bookmark with Archive', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertArrayHasKey('archive', $json);
        $this->assertIsString($json['archive']);
        $this->assertEquals($fileObjectIri, $json['archive'], 'archive should reference the created FileObject');
        $this->assertBookmarkOwnerResponse($json);

        // Verify we can retrieve the bookmark and it still has the archive
        $getResponse = $this->client->request('GET', $json['@id'], [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $retrievedBookmark = dump($getResponse->toArray());
        $this->assertEquals('Test Bookmark with Archive', $retrievedBookmark['title']);
        $this->assertArrayHasKey('archive', $retrievedBookmark);
        $this->assertEquals($fileObjectIri, $retrievedBookmark['archive'], 'archive should persist when retrieving the bookmark');
        $this->assertBookmarkOwnerResponse($retrievedBookmark);
    }

    public function testGetOwnBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        $bookmark = BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'My Bookmark',
            'url' => 'https://example.com',
            'tags' => new ArrayCollection([$tag1, $tag2]),
        ]);

        $this->assertUnauthorized('GET', "/api/users/me/bookmarks/{$bookmark->id}", [], 'Should not be able to access.');

        $response = $this->client->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertEquals('My Bookmark', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/api/users/me/bookmarks/{$bookmark->id}");
    }

    public function testEditOwnBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 3']);

        $bookmark = BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Original Title',
            'url' => 'https://original.com',
            'tags' => new ArrayCollection([$tag1, $tag2]),
        ]);

        $this->assertUnauthorized('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'title' => 'Updated Title',
            ],
        ]);

        $response = $this->client->request('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Updated Title',
                'url' => 'https://updated.com',
                'tags' => [
                    "/api/users/me/tags/{$tag2->slug}",
                    "/api/users/me/tags/{$tag3->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertEquals('Updated Title', $json['title']);
        $this->assertEquals('https://updated.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);

        $this->assertOtherUserCannotAccess('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['title' => 'Hacked Title'],
        ]);
    }

    public function testDeleteOwnBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['owner' => $user]);

        $this->assertUnauthorized('DELETE', "/api/users/me/bookmarks/{$bookmark->id}");

        $this->assertOtherUserCannotAccess('DELETE', "/api/users/me/bookmarks/{$bookmark->id}");

        $this->client->request('DELETE', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListPublicBookmarksOfUser(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Public Tag', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Another Tag', 'isPublic' => true]);

        BookmarkFactory::createMany(3, ['owner' => $user, 'isPublic' => true, 'tags' => new ArrayCollection([$tag1, $tag2])]);
        BookmarkFactory::createMany(2, ['owner' => $user, 'isPublic' => false]);

        $response = $this->client->request('GET', "/api/profile/{$user->username}/bookmarks");
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(3, $json['member']);
        $this->assertBookmarkProfileCollection($json['member']);
    }

    public function testGetPublicBookmark(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Public Tag', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Another Tag', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        $publicBookmark = BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Public Bookmark',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3]),
        ]);

        $privateBookmark = BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Private Bookmark',
            'url' => 'https://private.com',
            'isPublic' => false,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3]),
        ]);

        $response = $this->client->request('GET', "/api/profile/{$user->username}/bookmarks/{$publicBookmark->id}");
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertEquals('Public Bookmark', $json['title']);
        $this->assertEquals('https://public.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkProfileResponse($json);

        $this->client->request('GET', "/api/profile/{$user->username}/bookmarks/{$privateBookmark->id}");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetPublicBookmarkWithFilter(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag One', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Two', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Three', 'isPublic' => true]);
        $tagPrivate = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Two, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark Without Tag One',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag3, $tagPrivate]),
        ]);

        $response = $this->client->request('GET', "/api/profile/{$user->username}/bookmarks?tags=Tag%20One,Tag%20Two");
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(1, $json['member']);
        $this->assertEquals('Bookmark With Tag One, Two, Three, Private', $json['member'][0]['title']);
        $this->assertEquals('https://public.com', $json['member'][0]['url']);
        $this->assertIsArray($json['member'][0]['tags']);
        $this->assertCount(3, $json['member'][0]['tags']); // Only public tags
        $this->assertBookmarkProfileCollection($json['member']);

        $response = $this->client->request('GET', "/api/profile/{$user->username}/bookmarks?tags=Private%20Tag");
        $this->assertResponseIsSuccessful();

        $json = dump($response->toArray());

        $this->assertCount(0, $json['member']);
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->client->request($method, $url, $requestOptions);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Asserts that a bookmark response contains exactly the fields for bookmark:owner group.
     */
    private function assertBookmarkOwnerResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('createdAt', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);
        $this->assertArrayHasKey('owner', $json);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);

        $bookmarkFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'owner', 'isPublic', 'tags'];

        // Archive is optional, add it to expected fields if present
        if (isset($json['archive'])) {
            $expectedBookmarkFields[] = 'archive';
        }

        $this->assertEquals(
            $expectedBookmarkFields,
            array_values($bookmarkFields),
            'Response should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
        );
    }

    /**
     * Asserts that each bookmark in a collection contains exactly the fields for bookmark:owner group.
     */
    private function assertBookmarkOwnerCollection(array $bookmarks): void
    {
        foreach ($bookmarks as $bookmark) {
            $this->assertIsString($bookmark['id']);
            $this->assertIsString($bookmark['createdAt']);
            $this->assertIsString($bookmark['title']);
            $this->assertIsString($bookmark['url']);
            $this->assertIsBool($bookmark['isPublic']);
            $this->assertArrayHasKey('owner', $bookmark);
            $this->assertArrayHasKey('tags', $bookmark);
            $this->assertIsArray($bookmark['tags']);

            $bookmarkFields = array_filter(array_keys($bookmark), fn ($key) => !str_starts_with($key, '@'));
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'owner', 'isPublic', 'tags'];

            // Archive is optional, add it to expected fields if present
            if (isset($bookmark['archive'])) {
                $expectedBookmarkFields[] = 'archive';
            }

            $this->assertEquals(
                $expectedBookmarkFields,
                array_values($bookmarkFields),
                'Each bookmark in collection should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
            );
        }
    }

    /**
     * Asserts that a bookmark response contains exactly the fields for bookmark:profile group.
     */
    private function assertBookmarkProfileResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertIsString($json['id']);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);

        $bookmarkFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'tags'];
        $this->assertEquals(
            $expectedBookmarkFields,
            array_values($bookmarkFields),
            'Response should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
        );

        // Ensure owner and isPublic are not exposed in public profile
        $this->assertArrayNotHasKey('owner', $json, 'owner should not be in public profile response');
        $this->assertArrayNotHasKey('isPublic', $json, 'isPublic should not be in public profile response');
    }

    /**
     * Asserts that each bookmark in a collection contains exactly the fields for bookmark:profile group.
     */
    private function assertBookmarkProfileCollection(array $bookmarks): void
    {
        foreach ($bookmarks as $bookmark) {
            $this->assertIsString($bookmark['id']);
            $this->assertIsString($bookmark['createdAt']);
            $this->assertIsString($bookmark['title']);
            $this->assertIsString($bookmark['url']);
            $this->assertArrayHasKey('tags', $bookmark);
            $this->assertIsArray($bookmark['tags']);

            $bookmarkFields = array_filter(array_keys($bookmark), fn ($key) => !str_starts_with($key, '@'));
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'tags'];
            $this->assertEquals(
                $expectedBookmarkFields,
                array_values($bookmarkFields),
                'Each bookmark in public collection should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
            );

            // Ensure owner and isPublic are not exposed in public profile
            $this->assertArrayNotHasKey('owner', $bookmark, 'owner should not be in public profile response');
            $this->assertArrayNotHasKey('isPublic', $bookmark, 'isPublic should not be in public profile response');
        }
    }
}
