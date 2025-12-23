<?php

namespace App\Tests;

use App\Factory\BookmarkFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

        $this->request('GET', '/api/users/me/bookmarks', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(5, $json['collection']);
        $this->assertBookmarkOwnerCollection($json['collection']);
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
            'title' => 'Bookmark With Tag One, Two, Three, Private (first)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark Without Tag One (second)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Three (third)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag3]),
        ]);

        BookmarkFactory::createOne([
            'owner' => $user,
            'title' => 'Bookmark With Tag One, Two, Private (fourth)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tagPrivate]),
        ]);

        $this->request('GET', '/api/users/me/bookmarks?tags=tag-one,tag-two', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Only bookmark with both Tag One AND Tag Two should match
        $this->assertCount(2, $json['collection']);
        $this->assertEquals('Bookmark With Tag One, Two, Private (fourth)', $json['collection'][0]['title']);
        $this->assertEquals('https://public.com', $json['collection'][0]['url']);
        $this->assertIsArray($json['collection'][0]['tags']);
        $this->assertCount(3, $json['collection'][0]['tags']); // All tags
        $this->assertBookmarkOwnerCollection($json['collection']);

        $this->request('GET', '/api/users/me/bookmarks?tags=private-tag', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(3, $json['collection']);
    }

    public function testCreateBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        $this->assertUnauthorized('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
            ],
        ]);

        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
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

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('Test Bookmark', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);
    }

    public function testCreateBookmarkWithTagAsJsonObjectFails(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Bookmark With Tag Object',
                'url' => 'https://example.com',
                'tags' => [[
                    'name' => 'Some Tag',
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Supplying tags as JSON objects should fail with 422 Unprocessable Entity');

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('error', $json, 'Error should be present in response.');
    }

    public function testCreateBookmarkWithInvalidTagIri(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        // Use unexistant IRI (does not exist)
        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Bookmark With Invalid Tag',
                'url' => 'https://example.com',
                'tags' => ['/api/users/me/tags/nonexistent-tag-iri'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Supplying an invalid tag IRI should fail with 422 Unprocessable Entity.');

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('error', $json, 'Error message should be present in response.');

        // Use invalid IRI (does not parse)
        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Bookmark With Invalid Tag',
                'url' => 'https://example.com',
                'tags' => ['some-random-string'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Supplying an invalid tag IRI should fail with 422 Unprocessable Entity.');

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('error', $json, 'Error message should be present in response.');
    }

    #[DataProvider('fileFieldProvider')]
    public function testCreateBookmarkWithFile(string $fieldName, string $expectedTitle): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');
        $file = new UploadedFile(__DIR__ . '/data/image_01.jpg', 'image_01.jpg');

        $this->request('POST', '/api/users/me/files', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'auth_bearer' => $token,
            'extra' => [
                'files' => [
                    'file' => $file,
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $fileJson = $this->getResponseArray();
        $fileObjectIri = $fileJson['@iri'];

        $this->assertUnauthorized('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => $expectedTitle,
                'url' => 'https://example.com',
                $fieldName => $fileObjectIri,
            ],
        ]);

        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => $expectedTitle,
                'url' => 'https://example.com',
                $fieldName => $fileObjectIri,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals($expectedTitle, $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertArrayHasKey($fieldName, $json);
        $this->assertIsArray($json[$fieldName], "{$fieldName} should be an unfolded FileObject");
        $this->assertArrayHasKey('@iri', $json[$fieldName]);
        $this->assertEquals($fileObjectIri, $json[$fieldName]['@iri'], "{$fieldName} @iri should reference the created FileObject");
        $this->assertArrayHasKey('contentUrl', $json[$fieldName], "{$fieldName} should have contentUrl");
        $this->assertIsString($json[$fieldName]['contentUrl']);
        $this->assertBookmarkOwnerResponse($json);

        // Verify we can retrieve the bookmark and it still has the file field
        $this->request('GET', $json['@iri'], [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $retrievedBookmark = $this->dump($this->getResponseArray());
        $this->assertEquals($expectedTitle, $retrievedBookmark['title']);
        $this->assertArrayHasKey($fieldName, $retrievedBookmark);
        $this->assertIsArray($retrievedBookmark[$fieldName], "{$fieldName} should be an unfolded FileObject");
        $this->assertEquals($fileObjectIri, $retrievedBookmark[$fieldName]['@iri'], "{$fieldName} should persist when retrieving the bookmark");
        $this->assertBookmarkOwnerResponse($retrievedBookmark);
    }

    /**
     * @return array<mixed>
     */
    public static function fileFieldProvider(): array
    {
        return [
            'archive' => ['archive', 'Test Bookmark with Archive'],
            'mainImage' => ['mainImage', 'Test Bookmark with Main Image'],
        ];
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

        $this->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

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
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Updated Title',
            ],
        ]);

        $this->request('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Updated Title',
                'url' => 'https://updated.com', // We can not update url
                'tags' => [
                    "/api/users/me/tags/{$tag2->slug}", // TODO this is wrong, /api should not be part of the IRI IMHO
                    "/api/users/me/tags/{$tag3->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('Updated Title', $json['title']);
        $this->assertEquals('https://original.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);

        $this->assertOtherUserCannotAccess('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['title' => 'Hacked Title'],
        ]);
    }

    public function testDeleteOwnBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['owner' => $user]);

        $this->assertUnauthorized('DELETE', "/api/users/me/bookmarks/{$bookmark->id}");

        $this->assertOtherUserCannotAccess('DELETE', "/api/users/me/bookmarks/{$bookmark->id}");

        $this->request('DELETE', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
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

        $this->request('GET', "/api/profile/{$user->username}/bookmarks");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(3, $json['collection']);
        $this->assertBookmarkProfileCollection($json['collection']);
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

        $this->request('GET', "/api/profile/{$user->username}/bookmarks/{$publicBookmark->id}");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('Public Bookmark', $json['title']);
        $this->assertEquals('https://public.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkProfileResponse($json);

        $this->request('GET', "/api/profile/{$user->username}/bookmarks/{$privateBookmark->id}");
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

        $this->request('GET', "/api/profile/{$user->username}/bookmarks?tags=tag-one,tag-two");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(1, $json['collection']);
        $this->assertEquals('Bookmark With Tag One, Two, Three, Private', $json['collection'][0]['title']);
        $this->assertEquals('https://public.com', $json['collection'][0]['url']);
        $this->assertIsArray($json['collection'][0]['tags']);
        $this->assertCount(3, $json['collection'][0]['tags']); // Only public tags
        $this->assertBookmarkProfileCollection($json['collection']);

        $this->request('GET', "/api/profile/{$user->username}/bookmarks?tags=private-tag");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(0, $json['collection']);
    }

    public function testCursorBasedPagination(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        // Bookmark 120 is the newest (created last)
        for ($i = 1; $i <= 120; ++$i) {
            BookmarkFactory::createOne([
                'owner' => $user,
                'title' => "Bookmark {$i}",
                'url' => 'https://example.com',
            ]);
        }

        // Request the first page (no after parameter)
        $this->request('GET', '/api/users/me/bookmarks', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Should return 24 entries (first page)
        $this->assertCount(24, $json['collection']);
        $this->assertBookmarkOwnerCollection($json['collection']);

        // First entry should be Bookmark 120 (newest first, highest number)
        $this->assertEquals('Bookmark 120', $json['collection'][0]['title']);

        // Verify the order: should be descending (120, 119, 118, ...)
        for ($i = 0; $i < 24; ++$i) {
            $expectedTitle = 'Bookmark ' . (120 - $i);
            $this->assertEquals($expectedTitle, $json['collection'][$i]['title'], "Entry at index {$i} should be {$expectedTitle}");
        }

        // Get the last bookmark ID from the first page to use as cursor for the second page
        $lastBookmarkId = $json['collection'][23]['id'];
        $this->assertIsString($lastBookmarkId);

        // Request the second page using the last ID from the first page
        $this->request('GET', "/api/users/me/bookmarks?after={$lastBookmarkId}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(24, $json['collection']);
        $this->assertBookmarkOwnerCollection($json['collection']);

        // First entry should be Bookmark 96 (continuing from Bookmark 97 which was last on first page)
        $this->assertEquals('Bookmark 96', $json['collection'][0]['title']);

        // Verify the order: should be descending (96, 95, 94, ..., 73)
        for ($i = 0; $i < 24; ++$i) {
            $expectedTitle = 'Bookmark ' . (96 - $i);
            $this->assertEquals($expectedTitle, $json['collection'][$i]['title'], "Entry at index {$i} should be {$expectedTitle}");
        }
    }

    public function testCanNotAccessOtherUsersPrivateBookmark(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owner@example.com', 'owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $privateBookmark = BookmarkFactory::createOne([
            'owner' => $owner,
            'title' => 'Private Bookmark',
            'url' => 'https://private.com',
            'isPublic' => false,
        ]);

        // Owner can access their own private bookmark
        $this->request('GET', "/api/users/me/bookmarks/{$privateBookmark->id}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();

        // Other user cannot access owner's private bookmark
        $this->request('GET', "/api/users/me/bookmarks/{$privateBookmark->id}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to access private bookmark');
    }

    public function testCanNotEditOtherUsersBookmark(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owner@example.com', 'owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $bookmark = BookmarkFactory::createOne([
            'owner' => $owner,
            'title' => 'Original Bookmark',
            'url' => 'https://original.com',
        ]);

        // Owner can edit their own bookmark
        $this->request('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $ownerToken,
            'json' => ['title' => 'Updated By Owner'],
        ]);
        $this->assertResponseIsSuccessful();

        // Other user cannot edit owner's bookmark
        $this->request('PATCH', "/api/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $otherToken,
            'json' => ['title' => 'Hacked Bookmark'],
        ]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to edit bookmark');

        // Verify bookmark was not modified by other user
        $this->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $ownerToken]);
        $json = $this->getResponseArray();
        $this->assertEquals('Updated By Owner', $json['title'], 'Bookmark should not be modified by other user');
    }

    public function testCanNotDeleteOtherUsersBookmark(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owner@example.com', 'owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $bookmark = BookmarkFactory::createOne([
            'owner' => $owner,
            'title' => 'Bookmark To Delete',
            'url' => 'https://example.com',
        ]);

        // Other user cannot delete owner's bookmark
        $this->request('DELETE', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to delete bookmark');

        // Verify bookmark still exists
        $this->request('GET', "/api/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('Bookmark To Delete', $json['title'], 'Bookmark should still exist after failed deletion attempt');
    }

    #[DataProvider('domainExtractionProvider')]
    public function testDomainExtractionFromUrl(string $url, string $expectedDomain): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $this->request('POST', '/api/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Test Bookmark',
                'url' => $url,
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals($expectedDomain, $json['domain'], "Domain should be extracted correctly from URL: {$url}");

        // Verify domain persists when retrieving the bookmark
        $this->request('GET', $json['@iri'], [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $retrievedBookmark = $this->dump($this->getResponseArray());
        $this->assertEquals($expectedDomain, $retrievedBookmark['domain'], 'Domain should persist when retrieving the bookmark');
    }

    /**
     * @return array<mixed>
     */
    public static function domainExtractionProvider(): array
    {
        return [
            'https without www' => ['https://example.com/ok/file.html', 'example.com'],
            'http with www' => ['http://www.example.net/ok/file.html', 'example.net'],
            'https with www and subdomain' => ['https://www.subdomain.example.org/path/to/page', 'subdomain.example.org'],
            'http without www' => ['http://test.com/index.php', 'test.com'],
            'https with port' => ['https://example.com:8080/path', 'example.com'],
            'mobile' => ['https://m.example.com/path/to/file', 'example.com'],
        ];
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->request($method, $url, $requestOptions);
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
        $this->assertArrayHasKey('domain', $json);
        $this->assertIsString($json['domain']);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);
        $this->assertArrayHasKey('owner', $json);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);

        $bookmarkFields = array_keys($json);
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'owner', 'isPublic', 'tags', '@iri'];

        // Archive and mainImage are optional, add them to expected fields if present
        if (isset($json['archive'])) {
            $expectedBookmarkFields[] = 'archive';
            $this->assertIsArray($json['archive'], 'archive should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['archive']);
        }
        if (isset($json['mainImage'])) {
            $expectedBookmarkFields[] = 'mainImage';
            $this->assertIsArray($json['mainImage'], 'mainImage should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['mainImage']);
        }

        $this->assertEqualsCanonicalizing(
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
            $this->assertArrayHasKey('domain', $bookmark);
            $this->assertIsString($bookmark['domain']);
            $this->assertIsBool($bookmark['isPublic']);
            $this->assertArrayHasKey('owner', $bookmark);
            $this->assertArrayHasKey('tags', $bookmark);
            $this->assertIsArray($bookmark['tags']);

            $bookmarkFields = array_keys($bookmark);
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'owner', 'isPublic', 'tags', '@iri'];

            // Archive and mainImage are optional, add them to expected fields if present
            if (isset($bookmark['archive'])) {
                $expectedBookmarkFields[] = 'archive';
                $this->assertIsArray($bookmark['archive'], 'archive should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['archive']);
            }
            if (isset($bookmark['mainImage'])) {
                $expectedBookmarkFields[] = 'mainImage';
                $this->assertIsArray($bookmark['mainImage'], 'mainImage should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['mainImage']);
            }

            $this->assertEqualsCanonicalizing(
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
        $this->assertArrayHasKey('domain', $json);
        $this->assertIsString($json['domain']);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);

        $bookmarkFields = array_keys($json);
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'owner', 'tags', '@iri'];

        // Archive and mainImage are optional, add them to expected fields if present
        if (isset($json['archive'])) {
            $expectedBookmarkFields[] = 'archive';
            $this->assertIsArray($json['archive'], 'archive should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['archive']);
        }
        if (isset($json['mainImage'])) {
            $expectedBookmarkFields[] = 'mainImage';
            $this->assertIsArray($json['mainImage'], 'mainImage should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['mainImage']);
        }

        $this->assertEqualsCanonicalizing(
            $expectedBookmarkFields,
            array_values($bookmarkFields),
            'Response should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
        );

        // Ensure isPublic is not exposed in public profile
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
            $this->assertArrayHasKey('domain', $bookmark);
            $this->assertIsString($bookmark['domain']);
            $this->assertArrayHasKey('tags', $bookmark);
            $this->assertIsArray($bookmark['tags']);

            $bookmarkFields = array_keys($bookmark);
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'owner', 'tags', '@iri'];

            // Archive and mainImage are optional, add them to expected fields if present
            if (isset($bookmark['archive'])) {
                $expectedBookmarkFields[] = 'archive';
                $this->assertIsArray($bookmark['archive'], 'archive should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['archive']);
            }
            if (isset($bookmark['mainImage'])) {
                $expectedBookmarkFields[] = 'mainImage';
                $this->assertIsArray($bookmark['mainImage'], 'mainImage should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['mainImage']);
            }

            $this->assertEqualsCanonicalizing(
                $expectedBookmarkFields,
                array_values($bookmarkFields),
                'Each bookmark in public collection should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
            );

            // Ensure isPublic is not exposed in public profile
            $this->assertArrayNotHasKey('isPublic', $bookmark, 'isPublic should not be in public profile response');
        }
    }
}
