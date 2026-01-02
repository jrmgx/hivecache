<?php

namespace App\Tests;

use App\Factory\TagFactory;
use App\Factory\UserFactory;

class TagTest extends BaseApiTestCase
{
    public function testListOwnTags(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        TagFactory::createMany(3, ['owner' => $user]);

        $this->assertUnauthorized('GET', '/users/me/tags');

        $this->request('GET', '/users/me/tags', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(3, $json['collection']);
        $this->assertTagOwnerCollection($json['collection']);
    }

    public function testCreateTag(): void
    {
        [, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $this->assertUnauthorized('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'name' => 'Test Tag',
            ],
        ]);

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Test Tag',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Test Tag', $json['name']);
        $this->assertEquals('test-tag', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => '🎸',
                'slug' => 'force-slug', // Forbidden, ignore
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('🎸', $json['name']);
        $this->assertEquals('guitar', $json['slug']);
        $this->assertTagOwnerResponse($json);
    }

    public function testCreateTagWithSameNameReturnsExisting(): void
    {
        [, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'First Tag',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $firstJson = $this->dump($this->getResponseArray());
        $firstTagSlug = $firstJson['slug'];

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Second Tag',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $secondJson = $this->dump($this->getResponseArray());
        $secondTagSlug = $secondJson['slug'];

        $this->assertNotEquals($firstTagSlug, $secondTagSlug);

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'First Tag',
            ],
        ]);
        $this->assertResponseIsSuccessful();
        $thirdJson = $this->dump($this->getResponseArray());
        $thirdTagSlug = $thirdJson['slug'];

        $this->assertEquals($firstTagSlug, $thirdTagSlug, 'Creating a tag with the same name should return the existing tag');
        $this->assertEquals('First Tag', $thirdJson['name']);
        $this->assertEquals('first-tag', $thirdJson['slug']);
        $this->assertEquals($firstJson['id'], $thirdJson['id']);
        $this->assertTagOwnerResponse($thirdJson);
    }

    public function testCannotCreateMoreThan1000Tags(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        for ($i = 1; $i <= 1000; ++$i) {
            TagFactory::createOne([
                'owner' => $user,
                'name' => "Tag {$i}",
            ]);
        }

        // Attempt to create the 1001st tag
        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Exceeded Tag',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422, 'Should not be able to create more than 1000 tags');

        $json = $this->getResponseArray();
        $this->assertArrayHasKey('error', $json, 'Error message should be present in response.');
    }

    public function testGetOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'My Tag',
        ]);

        $this->assertUnauthorized('GET', "/users/me/tags/{$tag->slug}", [], 'Should not be able to access.');

        $this->request('GET', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('My Tag', $json['name']);
        $this->assertEquals('my-tag', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/users/me/tags/{$tag->slug}");
    }

    public function testEditOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Original Title',
        ]);

        $this->assertUnauthorized('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'name' => 'Updated Title',
            ],
        ]);

        $this->request('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Updated Title',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Updated Title', $json['name']);
        $this->assertEquals('updated-title', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->assertOtherUserCannotAccess('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['name' => 'Hacked Title'],
        ]);
    }

    public function testDeleteOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $tag = TagFactory::createOne(['owner' => $user]);

        $this->assertUnauthorized('DELETE', "/users/me/tags/{$tag->slug}");

        $this->assertOtherUserCannotAccess('DELETE', "/users/me/tags/{$tag->slug}");

        $this->request('DELETE', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('GET', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListPublicTagsOfUser(): void
    {
        $user = UserFactory::createOne([
            'username' => 'testuser',
        ]);

        TagFactory::createMany(3, ['owner' => $user, 'isPublic' => true]);
        TagFactory::createMany(2, ['owner' => $user, 'isPublic' => false]);

        $this->request('GET', "/profile/{$user->username}/tags");
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(3, $json['collection']);
        $this->assertTagProfileCollection($json['collection']);
    }

    public function testGetPublicTag(): void
    {
        $user = UserFactory::createOne([
            'username' => 'testuser',
        ]);

        $publicTag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Public Tag',
            'isPublic' => true,
        ]);

        $privateTag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Private Tag',
            'isPublic' => false,
        ]);

        $this->request('GET', "/profile/{$user->username}/tags/{$publicTag->slug}");
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Public Tag', $json['name']);
        $this->assertEquals('public-tag', $json['slug']);
        $this->assertTagProfileResponse($json);

        $this->request('GET', "/profile/{$user->username}/tags/{$privateTag->slug}");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateTagWithMeta(): void
    {
        [, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Tag With Meta',
                'meta' => [
                    'color' => 'blue',
                    'icon' => 'star',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Tag With Meta', $json['name']);
        $this->assertIsArray($json['meta']);
        $this->assertEquals('blue', $json['meta']['color']);
        $this->assertEquals('star', $json['meta']['icon']);
        $this->assertTagOwnerResponse($json);
    }

    public function testUpdateTagWithMeta(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Original Tag',
            'meta' => [],
        ]);

        $this->request('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'meta' => [
                    'color' => 'red',
                    'priority' => 'high',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Original Tag', $json['name']);
        $this->assertIsArray($json['meta']);
        $this->assertEquals('red', $json['meta']['color']);
        $this->assertEquals('high', $json['meta']['priority']);
        $this->assertTagOwnerResponse($json);
    }

    public function testUpdateTagMetaMergesNotOverwrites(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Tag With Existing Meta',
            'meta' => [
                'color' => 'blue',
                'icon' => 'star',
                'category' => 'important',
            ],
        ]);

        $this->request('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'meta' => [
                    'color' => 'red', // Update existing key
                    'priority' => 'high', // Add new key
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Tag With Existing Meta', $json['name']);
        $this->assertIsArray($json['meta']);
        // Updated value
        $this->assertEquals('red', $json['meta']['color']);
        // New key added
        $this->assertEquals('high', $json['meta']['priority']);
        // Existing keys preserved
        $this->assertEquals('star', $json['meta']['icon']);
        $this->assertEquals('important', $json['meta']['category']);
        $this->assertTagOwnerResponse($json);
    }

    public function testCanNotAccessOtherUsersPrivateTag(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('otheruser', 'test');

        $privateTag = TagFactory::createOne([
            'owner' => $owner,
            'name' => 'Private Tag',
            'isPublic' => false,
        ]);

        // Owner can access their own private tag
        $this->request('GET', "/users/me/tags/{$privateTag->slug}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();

        // Other user cannot access owner's private tag
        $this->request('GET', "/users/me/tags/{$privateTag->slug}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to access private tag');
    }

    public function testCanNotEditOtherUsersTag(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('otheruser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $owner,
            'name' => 'Original Tag',
        ]);

        // Owner can edit their own tag
        $this->request('PATCH', "/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $ownerToken,
            'json' => ['name' => 'Updated By Owner'],
        ]);
        $this->assertResponseIsSuccessful();
        $json = $this->dump($this->getResponseArray());
        $slug = $json['slug'];

        // Other user cannot edit owner's tag
        $this->request('PATCH', "/users/me/tags/{$slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $otherToken,
            'json' => ['name' => 'Hacked Tag'],
        ]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to edit tag');

        // Verify tag was not modified by other user
        $this->request('GET', "/users/me/tags/{$slug}", ['auth_bearer' => $ownerToken]);
        $json = $this->dump($this->getResponseArray());
        $this->assertEquals('Updated By Owner', $json['name'], 'Tag should not be modified by other user');
    }

    public function testCanNotDeleteOtherUsersTag(): void
    {
        [$owner, $ownerToken] = $this->createAuthenticatedUser('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUser('otheruser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $owner,
            'name' => 'Tag To Delete',
        ]);

        // Other user cannot delete owner's tag
        $this->request('DELETE', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $otherToken]);
        $this->assertResponseStatusCodeSame(404, 'Other user should not be able to delete tag');

        // Verify tag still exists
        $this->request('GET', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $ownerToken]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('Tag To Delete', $json['name'], 'Tag should still exist after failed deletion attempt');
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUser('otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->request($method, $url, $requestOptions);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Asserts that a tag response contains exactly the fields for tag:show:private group.
     */
    private function assertTagOwnerResponse(array $json): void
    {
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('slug', $json);
        $this->assertArrayHasKey('owner', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertIsArray($json['meta']);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);

        $tagFields = array_keys($json);
        $expectedTagFields = ['name', 'slug', 'owner', 'meta', 'isPublic', '@iri'];
        $this->assertEqualsCanonicalizing(
            $expectedTagFields,
            array_values($tagFields),
            'Response should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that each tag in a collection contains exactly the fields for tag:show:private group.
     */
    private function assertTagOwnerCollection(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->assertIsString($tag['name']);
            $this->assertIsString($tag['slug']);
            $this->assertArrayHasKey('owner', $tag);
            $this->assertIsArray($tag['meta']);
            $this->assertIsBool($tag['isPublic']);

            $tagFields = array_keys($tag);
            $expectedTagFields = ['name', 'slug', 'owner', 'meta', 'isPublic', '@iri'];
            $this->assertEqualsCanonicalizing(
                $expectedTagFields,
                array_values($tagFields),
                'Each tag in collection should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
            );

            $this->assertArrayHasKey('@iri', $tag);
            $this->assertValidUrl($tag['@iri'], '@iri should be a valid URL');
        }
    }

    /**
     * Asserts that a tag response contains exactly the fields for tag:show:public group.
     */
    private function assertTagProfileResponse(array $json): void
    {
        $tagFields = array_keys($json);
        $expectedTagFields = ['name', 'slug', '@iri'];
        $this->assertEqualsCanonicalizing(
            $expectedTagFields,
            array_values($tagFields),
            'Response should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
        );

        // Ensure owner and isPublic are not exposed in public profile
        $this->assertArrayNotHasKey('owner', $json, 'owner should not be in public profile response');
        $this->assertArrayNotHasKey('isPublic', $json, 'isPublic should not be in public profile response');

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that each tag in a collection contains exactly the fields for tag:show:public group.
     */
    private function assertTagProfileCollection(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->assertIsString($tag['name']);
            $this->assertIsString($tag['slug']);

            $tagFields = array_keys($tag);
            $expectedTagFields = ['name', 'slug', '@iri'];
            $this->assertEqualsCanonicalizing(
                $expectedTagFields,
                array_values($tagFields),
                'Each tag in public collection should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
            );

            // Ensure owner and isPublic are not exposed in public profile
            $this->assertArrayNotHasKey('owner', $tag, 'owner should not be in public profile response');
            $this->assertArrayNotHasKey('isPublic', $tag, 'isPublic should not be in public profile response');

            $this->assertArrayHasKey('@iri', $tag);
            $this->assertValidUrl($tag['@iri'], '@iri should be a valid URL');
        }
    }
}
