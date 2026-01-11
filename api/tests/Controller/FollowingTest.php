<?php

namespace App\Tests\Controller;

use App\Factory\AccountFactory;
use App\Factory\FollowingFactory;
use App\Tests\BaseApiTestCase;

class FollowingTest extends BaseApiTestCase
{
    public function testListOwnFollowing(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        FollowingFactory::createMany(10, ['owner' => $user]);

        $this->assertUnauthorized('GET', '/users/me/following');

        $this->request('GET', '/users/me/following', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(10, $json['collection']);
        $this->assertFollowingCollection($json['collection']);
    }

    public function testCreateFollowing(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Create an account to follow
        $accountToFollow = AccountFactory::createOne(['username' => 'followeduser']);
        $usernameWithInstance = $accountToFollow->username . '@' . $accountToFollow->instance;

        $this->assertUnauthorized('POST', "/users/me/following/{$usernameWithInstance}");

        $this->request('POST', "/users/me/following/{$usernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertIsString($json['id']);
        $this->assertArrayHasKey('account', $json);
        $this->assertIsArray($json['account']);
        $this->assertIsString($json['createdAt']);
        $this->assertEquals('followeduser', $json['account']['username']);
        $this->assertFollowingResponse($json);

        // Verify it appears in the list
        $this->request('GET', '/users/me/following', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $listJson = $this->getResponseArray();
        $this->assertGreaterThanOrEqual(1, \count($listJson['collection']));
    }

    public function testDeleteFollowing(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        // Create an account to follow
        $accountToFollow = AccountFactory::createOne(['username' => 'followeduser']);
        $usernameWithInstance = $accountToFollow->username . '@' . $accountToFollow->instance;

        // Create a following relationship
        $following = FollowingFactory::createOne([
            'owner' => $user,
            'account' => $accountToFollow,
        ]);

        $this->assertUnauthorized('DELETE', "/users/me/following/{$usernameWithInstance}");

        $this->request('DELETE', "/users/me/following/{$usernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Verify it's removed from the list
        $this->request('GET', '/users/me/following', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $listJson = $this->getResponseArray();
        $followingIds = array_column($listJson['collection'], 'id');
        $this->assertNotContains($following->id, $followingIds, 'Following should be removed from the list');
    }

    /**
     * Asserts that a following response contains exactly the fields for following:show:public group.
     */
    private function assertFollowingResponse(array $json): void
    {
        $this->assertIsString($json['id']);
        $this->assertArrayHasKey('account', $json);
        $this->assertIsArray($json['account']);
        $this->assertIsString($json['createdAt']);

        $followingFields = array_keys($json);
        $expectedFollowingFields = ['id', 'account', 'createdAt'];
        $this->assertEqualsCanonicalizing(
            $expectedFollowingFields,
            array_values($followingFields),
            'Response should contain exactly ' . implode(', ', $expectedFollowingFields) . ' fields'
        );

        // Assert account structure
        $this->assertArrayHasKey('username', $json['account']);
        $this->assertIsString($json['account']['username']);
        $this->assertArrayHasKey('instance', $json['account']);
        $this->assertIsString($json['account']['instance']);
        $this->assertArrayHasKey('@iri', $json['account']);
        $this->assertValidUrl($json['account']['@iri'], 'account @iri should be a valid URL');
    }

    /**
     * Asserts that each following in a collection contains exactly the fields for following:show:public group.
     */
    private function assertFollowingCollection(array $followings): void
    {
        foreach ($followings as $following) {
            $this->assertIsString($following['id']);
            $this->assertArrayHasKey('account', $following);
            $this->assertIsArray($following['account']);
            $this->assertIsString($following['createdAt']);

            $followingFields = array_keys($following);
            $expectedFollowingFields = ['id', 'account', 'createdAt'];
            $this->assertEqualsCanonicalizing(
                $expectedFollowingFields,
                array_values($followingFields),
                'Each following in collection should contain exactly ' . implode(', ', $expectedFollowingFields) . ' fields'
            );

            // Assert account structure
            $this->assertArrayHasKey('username', $following['account']);
            $this->assertIsString($following['account']['username']);
            $this->assertArrayHasKey('instance', $following['account']);
            $this->assertIsString($following['account']['instance']);
            $this->assertArrayHasKey('@iri', $following['account']);
            $this->assertValidUrl($following['account']['@iri'], 'account @iri should be a valid URL');
        }
    }
}
