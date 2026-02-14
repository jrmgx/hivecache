<?php

namespace App\Tests\Api\Controller;

use App\Factory\AccountFactory;
use App\Factory\UserFactory;
use App\Factory\UserTagFactory;
use App\Tests\BaseApiTestCase;

class TagTest extends BaseApiTestCase
{
    public function testListOwnTags(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        UserTagFactory::createMany(3, ['owner' => $user]);

        $this->assertUnauthorized('GET', '/users/me/tags');

        $this->request('GET', '/users/me/tags', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(3, $json['collection']);
        $this->assertTagOwnerCollection($json['collection']);
    }

    public function testCreateTag(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

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
                'name' => 'Force',
                'slug' => 'force-slug', // Forbidden, ignore
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('Force', $json['name']);
        $this->assertEquals('force', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->request('POST', '/users/me/tags', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => '🎸',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateTagWithSameNameReturnsExisting(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        for ($i = 1; $i <= 1000; ++$i) {
            UserTagFactory::createOne([
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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag = UserTagFactory::createOne([
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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag = UserTagFactory::createOne([
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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag = UserTagFactory::createOne(['owner' => $user]);

        $this->assertUnauthorized('DELETE', "/users/me/tags/{$tag->slug}");

        $this->assertOtherUserCannotAccess('DELETE', "/users/me/tags/{$tag->slug}");

        $this->request('DELETE', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->request('GET', "/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListPublicTagsOfUser(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        AccountFactory::createOneWithUsernameAndInstance('testuser', AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
        ]);

        UserTagFactory::createMany(3, ['owner' => $user, 'isPublic' => true]);
        UserTagFactory::createMany(2, ['owner' => $user, 'isPublic' => false]);

        $this->request('GET', "/profile/{$user->username}/tags");
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(3, $json['collection']);
        $this->assertTagProfileCollection($json['collection']);
    }

    public function testGetPublicTag(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser']);
        AccountFactory::createOneWithUsernameAndInstance('testuser', AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
        ]);

        $publicTag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'Public Tag',
            'isPublic' => true,
        ]);

        $privateTag = UserTagFactory::createOne([
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
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag = UserTagFactory::createOne([
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
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tag = UserTagFactory::createOne([
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
        [$owner, $ownerToken] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $privateTag = UserTagFactory::createOne([
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
        [$owner, $ownerToken] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $tag = UserTagFactory::createOne([
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
        [$owner, $ownerToken] = $this->createAuthenticatedUserAccount('owneruser', 'test');
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

        $tag = UserTagFactory::createOne([
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

    public function testUpdateTagConflictScenarios(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $tagOne = UserTagFactory::createOne(['owner' => $user, 'name' => 'one']);
        $tagTwo = UserTagFactory::createOne(['owner' => $user, 'name' => 'two']);

        // Scenario 1: Renaming "one" to "One" should not conflict (case change only)
        $this->request('PATCH', "/users/me/tags/{$tagOne->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['name' => 'One'],
        ]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('One', $json['name']);
        $this->assertEquals('one', $json['slug'], 'Slug should remain lowercase');

        // Scenario 2: Renaming "two" to "one" should conflict (different tag, same slug)
        $this->request('PATCH', "/users/me/tags/{$tagTwo->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => ['name' => 'one'],
        ]);
        $this->assertResponseStatusCodeSame(409, 'Should conflict when renaming to a name that creates a slug already used by another tag');
    }

    public function testUpdateTagSameNameDifferentUsers(): void
    {
        [$user1, $token1] = $this->createAuthenticatedUserAccount('user1', 'test');
        [$user2, $token2] = $this->createAuthenticatedUserAccount('user2', 'test');

        $tag1 = UserTagFactory::createOne(['owner' => $user1, 'name' => 'one']);
        $tag2 = UserTagFactory::createOne(['owner' => $user2, 'name' => 'one']);

        $this->request('PATCH', "/users/me/tags/{$tag1->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token1,
            'json' => ['name' => 'two'],
        ]);
        $this->assertResponseIsSuccessful();
        $json1 = $this->getResponseArray();
        $this->assertEquals('two', $json1['name']);
        $this->assertEquals('two', $json1['slug']);

        // Should succeed tags are user-scoped
        $this->request('PATCH', "/users/me/tags/{$tag2->slug}", [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token2,
            'json' => ['name' => 'two'],
        ]);
        $this->assertResponseIsSuccessful();
        $json2 = $this->getResponseArray();
        $this->assertEquals('two', $json2['name']);
        $this->assertEquals('two', $json2['slug']);

        $this->request('GET', "/users/me/tags/{$json1['slug']}", ['auth_bearer' => $token1]);
        $this->assertResponseIsSuccessful();
        $this->request('GET', "/users/me/tags/{$json2['slug']}", ['auth_bearer' => $token2]);
        $this->assertResponseIsSuccessful();
    }

    public function testGetPublicTagWithHtmlAcceptRedirects(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser', 'isPublic' => true]);
        AccountFactory::createOneWithUsernameAndInstance('testuser', AccountFactory::TEST_INSTANCE, [
            'owner' => $user,
        ]);

        $tag = UserTagFactory::createOne([
            'owner' => $user,
            'name' => 'Public Tag',
            'isPublic' => true,
        ]);

        $this->request('GET', "/profile/{$user->username}/tags/{$tag->slug}", [
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
        $this->assertStringStartsWith('https://', $queryParams['iri'], 'iri parameter should be an absolute URL starting with http://');
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUserAccount('otheruser', 'test');

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
        $this->assertArrayHasKey('meta', $json);
        $this->assertIsArray($json['meta']);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);

        $tagFields = array_keys($json);
        $expectedTagFields = ['name', 'slug', 'meta', 'isPublic', '@iri'];
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
            $this->assertIsArray($tag['meta']);
            $this->assertIsBool($tag['isPublic']);

            $tagFields = array_keys($tag);
            $expectedTagFields = ['name', 'slug', 'meta', 'isPublic', '@iri'];
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

        // Ensure isPublic is not exposed in public profile
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

            // Ensure isPublic is not exposed in public profile
            $this->assertArrayNotHasKey('isPublic', $tag, 'isPublic should not be in public profile response');

            $this->assertArrayHasKey('@iri', $tag);
            $this->assertValidUrl($tag['@iri'], '@iri should be a valid URL');
        }
    }
}
