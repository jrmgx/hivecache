<?php

namespace App\Tests\Controller;

use App\Factory\AccountFactory;
use App\Factory\BookmarkFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use App\Tests\BaseApiTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BookmarkTest extends BaseApiTestCase
{
    public function testListOwnBookmarks(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        BookmarkFactory::createMany(3, ['account' => $account, 'tags' => new ArrayCollection([$tag1, $tag2])]);
        BookmarkFactory::createMany(2, ['account' => $account, 'tags' => new ArrayCollection([$tag1])]);

        $this->assertUnauthorized('GET', '/users/me/bookmarks');

        $this->request('GET', '/users/me/bookmarks', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(5, $json['collection']);
        $this->assertBookmarkCollection($json['collection']);
    }

    public function testListOwnBookmarksWithFilter(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag One', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Two', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Three', 'isPublic' => true]);
        $tagPrivate = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark With Tag One, Two, Three, Private (first)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark Without Tag One (second)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark With Tag One, Three (third)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag3]),
        ]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark With Tag One, Two, Private (fourth)',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tagPrivate]),
        ]);

        $this->request('GET', '/users/me/bookmarks?tags=tag-one,tag-two', [
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
        $this->assertBookmarkCollection($json['collection']);

        $this->request('GET', '/users/me/bookmarks?tags=private-tag', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(3, $json['collection']);
    }

    public function testCreateBookmark(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        $this->assertUnauthorized('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
            ],
        ]);

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Test Bookmark',
                'url' => 'https://example.com',
                'tags' => [
                    "/users/me/tags/{$tag1->slug}",
                    "/users/me/tags/{$tag2->slug}",
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

    public function testCreateBookmarkWithSameUrlMergesTags(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 3']);
        $tag4 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 4']);

        $url = 'https://example.com/test-bookmark';

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'First Bookmark',
                'url' => $url,
                'tags' => [
                    "/users/me/tags/{$tag1->slug}",
                    "/users/me/tags/{$tag2->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $firstBookmark = $this->getResponseArray();
        $this->assertCount(2, $firstBookmark['tags'], 'First bookmark should have 2 tags');

        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Second Bookmark',
                'url' => $url,
                'tags' => [
                    "/users/me/tags/{$tag3->slug}",
                    "/users/me/tags/{$tag4->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Verify the second bookmark has all 4 tags merged
        $this->assertEquals('Second Bookmark', $json['title']);
        $this->assertEquals($url, $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(4, $json['tags'], 'Second bookmark should have all 4 tags merged');

        // Verify all tags are present
        $tagSlugs = array_map(fn ($tag) => $tag['slug'], $json['tags']);
        $this->assertContains($tag1->slug, $tagSlugs, 'Tag 1 should be present');
        $this->assertContains($tag2->slug, $tagSlugs, 'Tag 2 should be present');
        $this->assertContains($tag3->slug, $tagSlugs, 'Tag 3 should be present');
        $this->assertContains($tag4->slug, $tagSlugs, 'Tag 4 should be present');
        $this->assertBookmarkOwnerResponse($json);
    }

    public function testCreateBookmarkWithTagAsJsonObjectFails(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('POST', '/users/me/bookmarks', [
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
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Use nonexistent IRI (does not exist)
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Bookmark With Invalid Tag',
                'url' => 'https://example.com',
                'tags' => ['/users/me/tags/nonexistent-tag-iri'],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Supplying an invalid tag IRI should fail with 422 Unprocessable Entity.');

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('error', $json, 'Error message should be present in response.');

        // Use invalid IRI (does not parse)
        $this->request('POST', '/users/me/bookmarks', [
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
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');
        $file = new UploadedFile(__DIR__ . '/../data/image_01.jpg', 'image_01.jpg');

        $this->request('POST', '/users/me/files', [
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

        $this->assertUnauthorized('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => $expectedTitle,
                'url' => 'https://example.com',
                $fieldName => $fileObjectIri,
            ],
        ]);

        $this->request('POST', '/users/me/bookmarks', [
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
        $this->assertValidUrl($json[$fieldName]['@iri'], "{$fieldName} @iri should be a valid URL");
        $this->assertArrayHasKey('contentUrl', $json[$fieldName], "{$fieldName} should have contentUrl");
        $this->assertValidUrl($json[$fieldName]['contentUrl'], "{$fieldName} contentUrl should be a valid URL");
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
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'My Bookmark',
            'url' => 'https://example.com',
            'tags' => new ArrayCollection([$tag1, $tag2]),
        ]);

        $this->assertUnauthorized('GET', "/users/me/bookmarks/{$bookmark->id}", [], 'Should not be able to access.');

        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('My Bookmark', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkOwnerResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/users/me/bookmarks/{$bookmark->id}");
    }

    public function testEditOwnBookmark(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 3']);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Original Title',
            'url' => 'https://original.com',
            'tags' => new ArrayCollection([$tag1, $tag2]),
        ]);

        $this->assertUnauthorized('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'title' => 'Updated Title',
            ],
        ]);

        $this->request('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Updated Title',
                'url' => 'https://updated.com', // We can not update url
                'tags' => [
                    "/users/me/tags/{$tag2->slug}", // TODO this is wrong full path should be better
                    "/users/me/tags/{$tag3->slug}",
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

        $this->assertOtherUserCannotAccess('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['title' => 'Hacked Title'],
        ]);
    }

    public function testEditBookmarkTitlePreservesTags(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 3']);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Original Title',
            'url' => 'https://example.com',
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3]),
        ]);

        // Verify initial tags
        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();
        $initialJson = $this->getResponseArray();
        $this->assertCount(3, $initialJson['tags']);
        $initialTagSlugs = array_map(fn ($tag) => $tag['slug'], $initialJson['tags']);

        // Edit only the title, without including tags in the request
        $this->request('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Updated Title',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Verify title was updated
        $this->assertEquals('Updated Title', $json['title']);
        $this->assertEquals('https://example.com', $json['url']);

        // Verify tags remain unchanged
        $this->assertIsArray($json['tags']);
        $this->assertCount(3, $json['tags'], 'Tags should remain unchanged when only title is edited');
        $updatedTagSlugs = array_map(fn ($tag) => $tag['slug'], $json['tags']);
        $this->assertEqualsCanonicalizing($initialTagSlugs, $updatedTagSlugs, 'Tag slugs should remain the same');
        $this->assertBookmarkOwnerResponse($json);
    }

    public function testEditBookmarkTagsOnly(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 1']);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 2']);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag 3']);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Original Title',
            'url' => 'https://example.com',
            'tags' => new ArrayCollection([$tag1, $tag2]),
        ]);

        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $initialJson = $this->getResponseArray();
        $initialTitle = $initialJson['title'];
        $initialUrl = $initialJson['url'];
        $initialTagSlugs = array_map(fn ($tag) => $tag['slug'], $initialJson['tags']);

        // Update only tags
        $this->request('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'tags' => [
                    "/users/me/tags/{$tag2->slug}",
                    "/users/me/tags/{$tag3->slug}",
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Verify tags were updated
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags'], 'Tags should be updated');
        $updatedTagSlugs = array_map(fn ($tag) => $tag['slug'], $json['tags']);
        $this->assertContains($tag2->slug, $updatedTagSlugs, 'Tag 2 should be present');
        $this->assertContains($tag3->slug, $updatedTagSlugs, 'Tag 3 should be present');
        $this->assertNotContains($tag1->slug, $updatedTagSlugs, 'Tag 1 should be removed');

        // Verify other fields remained unchanged
        $this->assertEquals($initialTitle, $json['title'], 'Title should remain unchanged');
        $this->assertEquals($initialUrl, $json['url'], 'URL should remain unchanged');
        $this->assertBookmarkOwnerResponse($json);
    }

    public function testDeleteOwnBookmark(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $bookmark = BookmarkFactory::createOne(['account' => $account]);

        $this->assertUnauthorized('DELETE', "/users/me/bookmarks/{$bookmark->id}");

        $this->assertOtherUserCannotAccess('DELETE', "/users/me/bookmarks/{$bookmark->id}");

        $this->request('DELETE', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteBookmarkDeletesAllVersions(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

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
        $bookmark1 = $this->getResponseArray();
        $bookmark1Id = $bookmark1['id'];

        // Create second bookmark (Version 2) - this will mark Version 1 as outdated
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 2',
                'url' => $url,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $bookmark2 = $this->getResponseArray();
        $bookmark2Id = $bookmark2['id'];

        // Verify both bookmarks exist
        $this->request('GET', "/users/me/bookmarks/{$bookmark1Id}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $this->request('GET', "/users/me/bookmarks/{$bookmark2Id}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        // Delete the later bookmark (Version 2)
        $this->request('DELETE', "/users/me/bookmarks/{$bookmark2Id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        // Verify both bookmarks are deleted
        $this->request('GET', "/users/me/bookmarks/{$bookmark1Id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404, 'First bookmark should be deleted');
        $this->request('GET', "/users/me/bookmarks/{$bookmark2Id}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404, 'Second bookmark should be deleted');
    }

    public function testListPublicBookmarksOfUser(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Public Tag', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Another Tag', 'isPublic' => true]);

        BookmarkFactory::createMany(3, ['account' => $account, 'isPublic' => true, 'tags' => new ArrayCollection([$tag1, $tag2])]);
        BookmarkFactory::createMany(2, ['account' => $account, 'isPublic' => false]);

        $this->request('GET', "/profile/{$user->username}/bookmarks");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(3, $json['collection']);
        $this->assertBookmarkProfileCollection($json['collection']);
    }

    public function testGetPublicBookmark(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Public Tag', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Another Tag', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        $publicBookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Public Bookmark',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3]),
        ]);

        $privateBookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Private Bookmark',
            'url' => 'https://private.com',
            'isPublic' => false,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3]),
        ]);

        $this->request('GET', "/profile/{$user->username}/bookmarks/{$publicBookmark->id}");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertEquals('Public Bookmark', $json['title']);
        $this->assertEquals('https://public.com', $json['url']);
        $this->assertIsArray($json['tags']);
        $this->assertCount(2, $json['tags']);
        $this->assertBookmarkProfileResponse($json);

        $this->request('GET', "/profile/{$user->username}/bookmarks/{$privateBookmark->id}");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetPublicBookmarkWithFilter(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $tag1 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag One', 'isPublic' => true]);
        $tag2 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Two', 'isPublic' => true]);
        $tag3 = TagFactory::createOne(['owner' => $user, 'name' => 'Tag Three', 'isPublic' => true]);
        $tagPrivate = TagFactory::createOne(['owner' => $user, 'name' => 'Private Tag', 'isPublic' => false]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark With Tag One, Two, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark Without Tag One',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag2, $tag3, $tagPrivate]),
        ]);

        BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Bookmark With Tag One, Three, Private',
            'url' => 'https://public.com',
            'isPublic' => true,
            'tags' => new ArrayCollection([$tag1, $tag3, $tagPrivate]),
        ]);

        $this->request('GET', "/profile/{$user->username}/bookmarks?tags=tag-one,tag-two");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(1, $json['collection']);
        $this->assertEquals('Bookmark With Tag One, Two, Three, Private', $json['collection'][0]['title']);
        $this->assertEquals('https://public.com', $json['collection'][0]['url']);
        $this->assertIsArray($json['collection'][0]['tags']);
        $this->assertCount(3, $json['collection'][0]['tags']); // Only public tags
        $this->assertBookmarkProfileCollection($json['collection']);

        $this->request('GET', "/profile/{$user->username}/bookmarks?tags=private-tag");
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(0, $json['collection']);
    }

    public function testCreatePublicBookmarkThenPrivateWithSameUrlMakesBothPublic(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $url = 'https://example.com/test-bookmark';

        // Create bookmark0 with private visibility
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Private Bookmark 0',
                'url' => $url,
                'isPublic' => false,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $bookmark0 = $this->getResponseArray();
        $bookmark0Id = $bookmark0['id'];
        $this->assertFalse($bookmark0['isPublic'], 'Bookmark0 should be private');

        // Create bookmark1 with public visibility
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Public Bookmark',
                'url' => $url,
                'isPublic' => true,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $bookmark1 = $this->getResponseArray();
        $this->assertTrue($bookmark1['isPublic'], 'First bookmark should be public');

        // Create bookmark2 with same URL but private visibility
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Private Bookmark',
                'url' => $url,
                'isPublic' => false,
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $bookmark2 = $this->getResponseArray();

        // Verify second bookmark is public (should be forced to public because second one was public)
        $this->assertTrue($bookmark2['isPublic'], 'Second bookmark should be public even though private was requested');

        // Verify bookmark0 stays private
        $this->request('GET', "/users/me/bookmarks/{$bookmark0Id}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $retrievedBookmark0 = $this->getResponseArray();
        $this->assertFalse($retrievedBookmark0['isPublic'], 'Bookmark0 should remain private');
    }

    public function testCursorBasedPagination(): void
    {
        [$user, $token, $account] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Bookmark 120 is the newest (created last)
        for ($i = 1; $i <= 120; ++$i) {
            BookmarkFactory::createOne([
                'account' => $account,
                'title' => "Bookmark {$i}",
                'url' => 'https://example.com',
            ]);
        }

        // Request the first page (no after parameter)
        $this->request('GET', '/users/me/bookmarks', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Should return 24 entries (first page)
        $this->assertCount(24, $json['collection']);
        $this->assertBookmarkCollection($json['collection']);

        // First entry should be Bookmark 120 (newest first, highest number)
        $this->assertEquals('Bookmark 120', $json['collection'][0]['title']);

        // Verify the order: should be descending (120, 119, 118, ...)
        for ($i = 0; $i < 24; ++$i) {
            $expectedTitle = 'Bookmark ' . (120 - $i);
            $this->assertEquals($expectedTitle, $json['collection'][$i]['title'], "Entry at index {$i} should be {$expectedTitle}");
        }

        // Verify nextPage is present and is an absolute URL
        $this->assertArrayHasKey('nextPage', $json, 'nextPage should be present in response');
        $this->assertNotNull($json['nextPage'], 'nextPage should not be null when there are more results');
        $this->assertValidUrl($json['nextPage'], 'nextPage should be a valid absolute URL');

        // Get the last bookmark ID from the first page to use as cursor for the second page
        $lastBookmarkId = $json['collection'][23]['id'];
        $this->assertIsString($lastBookmarkId);

        // Request the second page using the last ID from the first page
        $this->request('GET', "/users/me/bookmarks?after={$lastBookmarkId}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(24, $json['collection']);
        $this->assertBookmarkCollection($json['collection']);

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
        [$owner, $ownerToken, $ownerAccount] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $privateBookmark = BookmarkFactory::createOne([
            'account' => $ownerAccount,
            'title' => 'Private Bookmark',
            'url' => 'https://private.com',
            'isPublic' => false,
        ]);

        // Owner can access their own private bookmark
        $this->request('GET', "/users/me/bookmarks/{$privateBookmark->id}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();

        // Other user cannot access owner's private bookmark
        $this->request('GET', "/users/me/bookmarks/{$privateBookmark->id}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to access private bookmark');
    }

    public function testCanNotEditOtherUsersBookmark(): void
    {
        [$owner, $ownerToken, $ownerAccount] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $bookmark = BookmarkFactory::createOne([
            'account' => $ownerAccount,
            'title' => 'Original Bookmark',
            'url' => 'https://original.com',
        ]);

        // Owner can edit their own bookmark
        $this->request('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $ownerToken,
            'json' => ['title' => 'Updated By Owner'],
        ]);
        $this->assertResponseIsSuccessful();

        // Other user cannot edit owner's bookmark
        $this->request('PATCH', "/users/me/bookmarks/{$bookmark->id}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $otherToken,
            'json' => ['title' => 'Hacked Bookmark'],
        ]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to edit bookmark');

        // Verify bookmark was not modified by other user
        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $ownerToken]);
        $json = $this->getResponseArray();
        $this->assertEquals('Updated By Owner', $json['title'], 'Bookmark should not be modified by other user');
    }

    public function testCanNotDeleteOtherUsersBookmark(): void
    {
        [$owner, $ownerToken, $ownerAccount] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $bookmark = BookmarkFactory::createOne([
            'account' => $ownerAccount,
            'title' => 'Bookmark To Delete',
            'url' => 'https://example.com',
        ]);

        // Other user cannot delete owner's bookmark
        $this->request('DELETE', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to delete bookmark');

        // Verify bookmark still exists
        $this->request('GET', "/users/me/bookmarks/{$bookmark->id}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('Bookmark To Delete', $json['title'], 'Bookmark should still exist after failed deletion attempt');
    }

    public function testGetPublicBookmarkWithHtmlAcceptRedirects(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser', 'isPublic' => true]);
        $account = AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $bookmark = BookmarkFactory::createOne([
            'account' => $account,
            'title' => 'Public Bookmark',
            'url' => 'https://example.com',
            'isPublic' => true,
        ]);

        $this->request('GET', "/profile/{$user->username}/bookmarks/{$bookmark->id}", [
            'headers' => ['Accept' => 'text/html'],
        ]);
        $this->assertResponseStatusCodeSame(302, 'GET request with Accept: text/html should return 302 redirect');
        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Response should be a redirect');

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($location, 'Location header should be present');
        $this->assertStringContainsString('?iri=', $location, 'Location URL should contain iri query parameter');

        // Parse the URL and verify the iri parameter is an absolute URL
        $parsedUrl = parse_url($location);
        $this->assertIsArray($parsedUrl, 'Location should be a valid URL');
        $this->assertArrayHasKey('query', $parsedUrl, 'Location URL should have query parameters');
        parse_str($parsedUrl['query'], $queryParams);
        $this->assertArrayHasKey('iri', $queryParams, 'Query parameters should contain iri');
        $this->assertStringStartsWith('http://', $queryParams['iri'], 'iri parameter should be an absolute URL starting with http://');
    }

    #[DataProvider('domainExtractionProvider')]
    public function testDomainExtractionFromUrl(string $url, string $expectedDomain): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('POST', '/users/me/bookmarks', [
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

    public function testBookmarkHistory(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Create first bookmark (Version 1)
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 1',
                // We will even play a bit with the url to test the normalization process
                'url' => 'https://example.com/bookmark/dir/file.html?param=some&second=2',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Create second bookmark (Version 2) - this will mark Version 1 as outdated
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 2',
                'url' => 'https://example.com/bookmark/dir/file.html?second=2&param=some',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Create third bookmark (Version 3) - this will mark Version 2 as outdated
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 3',
                'url' => 'https://example.com/bookmark/dir/file.html?param=some&second=2',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Create fourth bookmark (Version 4) - this will mark Version 3 as outdated
        $this->request('POST', '/users/me/bookmarks', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'title' => 'Version 4',
                'url' => 'https://example.com/bookmark/dir/file.html?param=some&second=2',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $bookmark4 = $this->getResponseArray();

        $this->assertUnauthorized('GET', "/users/me/bookmarks/{$bookmark4['id']}/history");

        // Call history endpoint on the latest bookmark (Version 4)
        $this->request('GET', "/users/me/bookmarks/{$bookmark4['id']}/history", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        // Should return 3 bookmarks (versions 3, 2, 1) - all except the latest (version 4)
        $this->assertArrayHasKey('collection', $json);
        $this->assertCount(3, $json['collection']);

        // Verify the order: should be descending (Version 3, Version 2, Version 1)
        $this->assertEquals('Version 3', $json['collection'][0]['title'], 'First bookmark should be Version 3');
        $this->assertEquals('Version 2', $json['collection'][1]['title'], 'Second bookmark should be Version 2');
        $this->assertEquals('Version 1', $json['collection'][2]['title'], 'Third bookmark should be Version 1');
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->request($method, $url, $requestOptions);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Asserts that a bookmark response contains exactly the fields for bookmark:show:private group.
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
        $this->assertArrayHasKey('account', $json);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);
        $this->assertArrayHasKey('instance', $json);
        $this->assertIsString($json['instance']);

        $bookmarkFields = array_keys($json);
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'account', 'isPublic', 'tags', 'instance', '@iri'];

        // Archive and mainImage are optional, add them to expected fields if present
        if (isset($json['archive'])) {
            $expectedBookmarkFields[] = 'archive';
            $this->assertIsArray($json['archive'], 'archive should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['archive']);
            $this->assertValidUrl($json['archive']['contentUrl'], 'archive contentUrl should be a valid URL');
        }
        if (isset($json['mainImage'])) {
            $expectedBookmarkFields[] = 'mainImage';
            $this->assertIsArray($json['mainImage'], 'mainImage should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['mainImage']);
            $this->assertValidUrl($json['mainImage']['contentUrl'], 'mainImage contentUrl should be a valid URL');
        }

        $this->assertEqualsCanonicalizing(
            $expectedBookmarkFields,
            array_values($bookmarkFields),
            'Response should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that each bookmark in a collection contains exactly the fields for bookmark:show:private group.
     */

    /**
     * Asserts that a bookmark response contains exactly the fields for bookmark:show:public group.
     */
    private function assertBookmarkProfileResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertIsString($json['id']);
        $this->assertArrayHasKey('domain', $json);
        $this->assertIsString($json['domain']);
        $this->assertArrayHasKey('tags', $json);
        $this->assertIsArray($json['tags']);
        $this->assertArrayHasKey('instance', $json);
        $this->assertIsString($json['instance']);

        $bookmarkFields = array_keys($json);
        $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'account', 'tags', 'instance', '@iri'];

        // Archive and mainImage are optional, add them to expected fields if present
        if (isset($json['archive'])) {
            $expectedBookmarkFields[] = 'archive';
            $this->assertIsArray($json['archive'], 'archive should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['archive']);
            $this->assertValidUrl($json['archive']['contentUrl'], 'archive contentUrl should be a valid URL');
        }
        if (isset($json['mainImage'])) {
            $expectedBookmarkFields[] = 'mainImage';
            $this->assertIsArray($json['mainImage'], 'mainImage should be an unfolded FileObject');
            $this->assertArrayHasKey('contentUrl', $json['mainImage']);
            $this->assertValidUrl($json['mainImage']['contentUrl'], 'mainImage contentUrl should be a valid URL');
        }

        $this->assertEqualsCanonicalizing(
            $expectedBookmarkFields,
            array_values($bookmarkFields),
            'Response should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
        );

        // Ensure isPublic is not exposed in public profile
        $this->assertArrayNotHasKey('isPublic', $json, 'isPublic should not be in public profile response');

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that each bookmark in a collection contains exactly the fields for bookmark:show:public group.
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
            $this->assertArrayHasKey('instance', $bookmark);
            $this->assertIsString($bookmark['instance']);

            $bookmarkFields = array_keys($bookmark);
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'account', 'tags', 'instance', '@iri'];

            // Archive and mainImage are optional, add them to expected fields if present
            if (isset($bookmark['archive'])) {
                $expectedBookmarkFields[] = 'archive';
                $this->assertIsArray($bookmark['archive'], 'archive should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['archive']);
                $this->assertValidUrl($bookmark['archive']['contentUrl'], 'archive contentUrl should be a valid URL');
            }
            if (isset($bookmark['mainImage'])) {
                $expectedBookmarkFields[] = 'mainImage';
                $this->assertIsArray($bookmark['mainImage'], 'mainImage should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['mainImage']);
                $this->assertValidUrl($bookmark['mainImage']['contentUrl'], 'mainImage contentUrl should be a valid URL');
            }

            $this->assertEqualsCanonicalizing(
                $expectedBookmarkFields,
                array_values($bookmarkFields),
                'Each bookmark in public collection should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
            );

            // Ensure isPublic is not exposed in public profile
            $this->assertArrayNotHasKey('isPublic', $bookmark, 'isPublic should not be in public profile response');

            $this->assertArrayHasKey('@iri', $bookmark);
            $this->assertValidUrl($bookmark['@iri'], '@iri should be a valid URL');
        }
    }
}
