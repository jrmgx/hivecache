<?php

namespace App\Tests\Controller;

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
            $this->assertArrayHasKey('username', $follower['account']);
            $this->assertIsString($follower['account']['username']);
            $this->assertArrayHasKey('instance', $follower['account']);
            $this->assertIsString($follower['account']['instance']);
            $this->assertArrayHasKey('@iri', $follower['account']);
            $this->assertValidUrl($follower['account']['@iri'], 'account @iri should be a valid URL');
        }
    }
}
