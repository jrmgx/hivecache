<?php

namespace App\Tests;

use App\Factory\TagFactory;
use App\Factory\UserFactory;

class TagTest extends BaseApiTestCase
{
    public function testListOwnTags(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        TagFactory::createMany(3, ['owner' => $user]);

        $this->assertUnauthorized('GET', '/api/users/me/tags');

        $response = $this->client->request('GET', '/api/users/me/tags', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertCount(3, $json['member']);
        $this->assertTagOwnerCollection($json['member']);
    }

    public function testCreateTag(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $this->assertUnauthorized('POST', '/api/users/me/tags', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Test Tag',
            ],
        ]);

        $response = $this->client->request('POST', '/api/users/me/tags', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Test Tag',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('Test Tag', $json['name']);
        $this->assertEquals('test-tag', $json['slug']);
        $this->assertTagOwnerResponse($json);
    }

    public function testGetOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'My Tag',
        ]);

        $this->assertUnauthorized('GET', "/api/users/me/tags/{$tag->slug}", [], 'Should not be able to access.');

        $response = $this->client->request('GET', "/api/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('My Tag', $json['name']);
        $this->assertEquals('my-tag', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->assertOtherUserCannotAccess('GET', "/api/users/me/tags/{$tag->slug}");
    }

    public function testEditOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag = TagFactory::createOne([
            'owner' => $user,
            'name' => 'Original Title',
        ]);

        $this->assertUnauthorized('PATCH', "/api/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Title',
            ],
        ]);

        $response = $this->client->request('PATCH', "/api/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Updated Title',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('Updated Title', $json['name']);
        $this->assertEquals('updated-title', $json['slug']);
        $this->assertTagOwnerResponse($json);

        $this->assertOtherUserCannotAccess('PATCH', "/api/users/me/tags/{$tag->slug}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Hacked Title'],
        ]);
    }

    public function testDeleteOwnTag(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'testuser', 'test');

        $tag = TagFactory::createOne(['owner' => $user]);

        $this->assertUnauthorized('DELETE', "/api/users/me/tags/{$tag->slug}");

        $this->assertOtherUserCannotAccess('DELETE', "/api/users/me/tags/{$tag->slug}");

        $this->client->request('DELETE', "/api/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/users/me/tags/{$tag->slug}", ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testListPublicTagsOfUser(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);

        TagFactory::createMany(3, ['owner' => $user, 'isPublic' => true]);
        TagFactory::createMany(2, ['owner' => $user, 'isPublic' => false]);

        $response = $this->client->request('GET', "/api/profile/{$user->username}/tags");
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertCount(3, $json['member']);
        $this->assertTagProfileCollection($json['member']);
    }

    public function testGetPublicTag(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
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

        $response = $this->client->request('GET', "/api/profile/{$user->username}/tags/{$publicTag->slug}");
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('Public Tag', $json['name']);
        $this->assertEquals('public-tag', $json['slug']);
        $this->assertTagProfileResponse($json);

        $this->client->request('GET', "/api/profile/{$user->username}/tags/{$privateTag->slug}");
        $this->assertResponseStatusCodeSame(404);
    }

    private function assertOtherUserCannotAccess(string $method, string $url, array $options = []): void
    {
        [, $otherToken] = $this->createAuthenticatedUser('other@example.com', 'otheruser', 'test');

        $requestOptions = array_merge($options, ['auth_bearer' => $otherToken]);
        $this->client->request($method, $url, $requestOptions);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Asserts that a tag response contains exactly the fields for tag:owner group.
     */
    private function assertTagOwnerResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('slug', $json);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);
        $this->assertArrayHasKey('owner', $json);

        $tagFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedTagFields = ['id', 'name', 'slug', 'owner', 'isPublic'];
        $this->assertEquals(
            $expectedTagFields,
            array_values($tagFields),
            'Response should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
        );
    }

    /**
     * Asserts that each tag in a collection contains exactly the fields for tag:owner group.
     */
    private function assertTagOwnerCollection(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->assertIsString($tag['id']);
            $this->assertIsString($tag['name']);
            $this->assertIsString($tag['slug']);
            $this->assertIsBool($tag['isPublic']);
            $this->assertArrayHasKey('owner', $tag);

            $tagFields = array_filter(array_keys($tag), fn ($key) => !str_starts_with($key, '@'));
            $expectedTagFields = ['id', 'name', 'slug', 'owner', 'isPublic'];
            $this->assertEquals(
                $expectedTagFields,
                array_values($tagFields),
                'Each tag in collection should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
            );
        }
    }

    /**
     * Asserts that a tag response contains exactly the fields for tag:profile group.
     */
    private function assertTagProfileResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertIsString($json['id']);

        $tagFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedTagFields = ['id', 'name', 'slug'];
        $this->assertEquals(
            $expectedTagFields,
            array_values($tagFields),
            'Response should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
        );

        // Ensure owner and isPublic are not exposed in public profile
        $this->assertArrayNotHasKey('owner', $json, 'owner should not be in public profile response');
        $this->assertArrayNotHasKey('isPublic', $json, 'isPublic should not be in public profile response');
    }

    /**
     * Asserts that each tag in a collection contains exactly the fields for tag:profile group.
     */
    private function assertTagProfileCollection(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->assertIsString($tag['id']);
            $this->assertIsString($tag['name']);
            $this->assertIsString($tag['slug']);

            $tagFields = array_filter(array_keys($tag), fn ($key) => !str_starts_with($key, '@'));
            $expectedTagFields = ['id', 'name', 'slug'];
            $this->assertEquals(
                $expectedTagFields,
                array_values($tagFields),
                'Each tag in public collection should contain exactly ' . implode(', ', $expectedTagFields) . ' fields'
            );

            // Ensure owner and isPublic are not exposed in public profile
            $this->assertArrayNotHasKey('owner', $tag, 'owner should not be in public profile response');
            $this->assertArrayNotHasKey('isPublic', $tag, 'isPublic should not be in public profile response');
        }
    }
}
