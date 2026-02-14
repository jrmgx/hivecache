<?php

namespace App\Tests\Api\Controller;

use App\Factory\FollowerFactory;
use App\Tests\BaseApiTestCase;

class FollowerTest extends BaseApiTestCase
{
    public function testListOwnFollowers(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        FollowerFactory::createMany(10, ['owner' => $user]);

        $this->assertUnauthorized('GET', '/users/me/followers');

        $this->request('GET', '/users/me/followers', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertCount(10, $json['collection']);
        $this->assertFollowerCollection($json['collection']);
    }

    /**
     * Asserts that each follower in a collection contains exactly the fields for follower:show:public group.
     */
    private function assertFollowerCollection(array $followers): void
    {
        foreach ($followers as $follower) {
            $this->assertIsString($follower['id']);
            $this->assertArrayHasKey('account', $follower);
            $this->assertIsArray($follower['account']);
            $this->assertIsString($follower['createdAt']);

            $followerFields = array_keys($follower);
            $expectedFollowerFields = ['id', 'account', 'createdAt'];
            $this->assertEqualsCanonicalizing(
                $expectedFollowerFields,
                array_values($followerFields),
                'Each follower in collection should contain exactly ' . implode(', ', $expectedFollowerFields) . ' fields'
            );

            // Assert account structure
            $accountFields = array_keys($follower['account']);
            $expectedAccountFields = ['username', '@iri', 'instance', 'inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'];
            $this->assertEqualsCanonicalizing(
                $expectedAccountFields,
                array_values($accountFields),
                'Account should contain exactly ' . implode(', ', $expectedAccountFields) . ' fields'
            );

            $this->assertIsString($follower['account']['username']);
            $this->assertIsString($follower['account']['instance']);
            $this->assertValidUrl($follower['account']['@iri'], 'account @iri should be a valid URL');

            // URL fields are nullable, but if present should be valid URLs
            foreach (['inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'] as $urlField) {
                if (null !== $follower['account'][$urlField]) {
                    $this->assertValidUrl($follower['account'][$urlField], "account {$urlField} should be a valid URL if present");
                }
            }
        }
    }
}
