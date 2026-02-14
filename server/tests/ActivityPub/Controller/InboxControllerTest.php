<?php

namespace App\Tests\ActivityPub\Controller;

use App\Factory\AccountFactory;
use App\Tests\BaseApiTestCase;
use Symfony\Component\HttpClient\HttpClient;

class InboxControllerTest extends BaseApiTestCase
{
    public function testInboxPostCreatesFollower(): void
    {
        // First: create a user on the external/distant server
        $external = $this->createExternalAccount('external_' . uniqid(), 'password123');

        // Second: follow from our server the user from the external/distant server
        $token = $this->getToken('one', 'password');
        $externalUsername = $external['username'];
        $externalUsernameWithInstance = $external['username'] . '@' . $external['instance'];
        $this->request('POST', "/users/me/following/{$externalUsernameWithInstance}", [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseIsSuccessful();

        // From here the external/distant server will receive our follow activity in its inbox
        $httpClient = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);
        $followersResponse = $httpClient->request(
            'GET', 'https://' . AccountFactory::TEST_AP_SERVER_INSTANCE . '/ap/u/' . $externalUsername . '/followers?after=first', [
                'headers' => ['accept' => 'application/activity+json'],
                'timeout' => 5,
            ]
        );
        $this->assertEquals(200, $followersResponse->getStatusCode());
        $followersData = $followersResponse->toArray();
        $this->assertArrayHasKey('orderedItems', $followersData);
        $ourAccountUri = 'https://' . $this->container->getParameter('instanceHost') . '/profile/one';
        $this->assertContains($ourAccountUri, $followersData['orderedItems'], 'Our account should appear in external user followers');
    }
}
