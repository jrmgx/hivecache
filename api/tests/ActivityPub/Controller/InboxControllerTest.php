<?php

namespace App\Tests\ActivityPub\Controller;

use App\Entity\Follower;
use App\Tests\BaseApiTestCase;
use Symfony\Component\HttpClient\HttpClient;

class InboxControllerTest extends BaseApiTestCase
{
    public function testInboxPostCreatesFollower(): void
    {
        self::markTestSkipped();
        $httpClient = HttpClient::create();
        $externalServerUrl = 'http://external_ap_server.test:8000';
        $externalUsername = 'external' . uniqid();

        // First step create an user on the external/distant server
        $registerResponse = $httpClient->request('POST', $externalServerUrl . '/register', [
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $externalUsername,
                'password' => 'password123',
            ],
            'timeout' => 5,
        ]);
        $this->assertEquals(200, $registerResponse->getStatusCode(), 'User registration should succeed');
        $externalUserData = $registerResponse->toArray();
        $this->assertArrayHasKey('account', $externalUserData, 'Response should include account');
        $externalAccountUri = $externalUserData['account']['@iri'];

        // Second step create an user on our server
        [$internalUser, $internalAccount] = $this->createUserAccountWithPassword('internaluser', 'password123');
        $instanceHost = $this->container->getParameter('instanceHost');
        // $usernameWithInstance = $internalUser->username . '@' . $instanceHost;

        // Thrid step follow from our server the user from the external/distant server
        // TODO implement

        // From here the external/distant server will receive our follow activity in its inbox
        // TODO test that the external/distant user shows us as a follower
    }
}
