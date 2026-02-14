<?php

namespace App\Tests\Api\Controller;

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

        $json = $this->dump($this->getResponseArray());

        $this->assertCount(10, $json['collection']);
        $this->assertFollowingCollection($json['collection']);
    }

    public function testCreateFollowing(): void
    {
        $token = $this->getToken('one', 'password');

        $external = $this->createExternalAccount('followeduser_' . uniqid(), 'password123');
        $usernameWithInstance = $external['username'] . '@' . $external['instance'];

        $this->assertUnauthorized('POST', "/users/me/following/{$usernameWithInstance}");

        $this->request('POST', "/users/me/following/{$usernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->dump($this->getResponseArray());

        $this->assertIsString($json['id']);
        $this->assertArrayHasKey('account', $json);
        $this->assertIsArray($json['account']);
        $this->assertIsString($json['createdAt']);
        $this->assertEquals($external['username'], $json['account']['username']);
        $this->assertFollowingResponse($json);

        // Verify it appears in the list
        $this->request('GET', '/users/me/following', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $listJson = $this->dump($this->getResponseArray());
        $this->assertGreaterThanOrEqual(1, \count($listJson['collection']));
    }

    public function testDeleteFollowing(): void
    {
        $token = $this->getToken('one', 'password');

        $external = $this->createExternalAccount('followeduser_' . uniqid(), 'password123');
        $usernameWithInstance = $external['username'] . '@' . $external['instance'];

        // First, create the following by following the account
        $this->request('POST', "/users/me/following/{$usernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();
        $createJson = $this->dump($this->getResponseArray());
        $followingId = $createJson['id'];

        $this->assertUnauthorized('DELETE', "/users/me/following/{$usernameWithInstance}");

        $this->request('DELETE', "/users/me/following/{$usernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Verify it's removed from the list
        $this->request('GET', '/users/me/following', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $listJson = $this->dump($this->getResponseArray());
        $followingIds = array_column($listJson['collection'], 'id');
        $this->assertNotContains($followingId, $followingIds, 'Following should be removed from the list');
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
        $accountFields = array_keys($json['account']);
        $expectedAccountFields = ['username', '@iri', 'instance', 'inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'];
        $this->assertEqualsCanonicalizing(
            $expectedAccountFields,
            array_values($accountFields),
            'Account should contain exactly ' . implode(', ', $expectedAccountFields) . ' fields'
        );

        $this->assertIsString($json['account']['username']);
        $this->assertIsString($json['account']['instance']);
        $this->assertValidUrl($json['account']['@iri'], 'account @iri should be a valid URL');

        // URL fields are nullable, but if present should be valid URLs
        foreach (['inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'] as $urlField) {
            if (null !== $json['account'][$urlField]) {
                $this->assertValidUrl($json['account'][$urlField], "account {$urlField} should be a valid URL if present");
            }
        }
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
            $accountFields = array_keys($following['account']);
            $expectedAccountFields = ['username', '@iri', 'instance', 'inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'];
            $this->assertEqualsCanonicalizing(
                $expectedAccountFields,
                array_values($accountFields),
                'Account should contain exactly ' . implode(', ', $expectedAccountFields) . ' fields'
            );

            $this->assertIsString($following['account']['username']);
            $this->assertIsString($following['account']['instance']);
            $this->assertValidUrl($following['account']['@iri'], 'account @iri should be a valid URL');

            // URL fields are nullable, but if present should be valid URLs
            foreach (['inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'] as $urlField) {
                if (null !== $following['account'][$urlField]) {
                    $this->assertValidUrl($following['account'][$urlField], "account {$urlField} should be a valid URL if present");
                }
            }
        }
    }
}
