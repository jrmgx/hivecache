<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Factory\AccountFactory;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use App\Tests\BaseApiTestCase;

class UserTest extends BaseApiTestCase
{
    public function testGetOwnProfile(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('test', 'test');

        $this->assertUnauthorized('GET', '/users/me');

        $this->request('GET', '/users/me', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('test', $json['username']);
        $this->assertUserOwnerResponse($json);
    }

    public function testUpdateOwnProfile(): void
    {
        [, $originalToken] = $this->createAuthenticatedUserAccount('test', 'test');

        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $originalToken,
            'json' => [
                'username' => 'updateduser',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('updateduser', $json['username']);
        $this->assertUserOwnerResponse($json);

        $ussernameUpdatedToken = $this->getToken('updateduser', 'test');

        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $ussernameUpdatedToken,
            'json' => [
                'password' => 'newpassword',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $passwordUpdatedToken = $this->getToken('updateduser', 'newpassword');
        $this->assertNotEmpty($passwordUpdatedToken);
    }

    public function testPasswordChangeInvalidatesOldJwt(): void
    {
        [, $oldToken] = $this->createAuthenticatedUserAccount('test', 'oldpassword');

        // Verify user can see his profile with the original token
        $this->request('GET', '/users/me', ['auth_bearer' => $oldToken]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('test', $json['username']);

        // Change password
        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $oldToken,
            'json' => [
                'password' => 'newpassword',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        // Try to access profile with old token - should fail
        $this->request('GET', '/users/me', ['auth_bearer' => $oldToken]);
        $this->assertResponseStatusCodeSame(401, 'Old JWT token should be invalid after password change.');

        // Re-login with new password to get a new token
        $newToken = $this->getToken('test', 'newpassword');
        $this->assertNotEmpty($newToken, 'Should be able to login with new password.');

        // Verify user can see his profile with the new token
        $this->request('GET', '/users/me', ['auth_bearer' => $newToken]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('test', $json['username']);
        $this->assertUserOwnerResponse($json);
    }

    public function testDeleteOwnProfile(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('test', 'test');

        $this->assertUnauthorized('DELETE', '/users/me', [], 'Deletion authorized but it should not.');

        $this->request('DELETE', '/users/me', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Verify user is deleted (from database)
        $userRepository = $this->getUserRepository();
        $deletedUser = $userRepository->find($user->id);
        $this->assertNull($deletedUser, 'User should be deleted from database.');

        // Verify user is deleted (API check)
        $this->request('GET', '/users/me', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(404, 'User is deleted but still have access.');
    }

    public function testShowPublicProfile(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('test', 'test');

        $user = UserFactory::createOne(['username' => 'publicuser', 'isPublic' => true]);
        AccountFactory::createOne(['username' => 'publicuser', 'owner' => $user]);

        $privateUser = UserFactory::createOne(['username' => 'privateuser', 'isPublic' => false]);
        AccountFactory::createOne(['username' => 'privateuser', 'owner' => $privateUser]);

        // Test accessing public profile via username (no JWT)
        $this->request('GET', '/profile/publicuser');
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('publicuser', $json['username']);
        $this->assertAccountResponse($json);

        // Test accessing private profile (should work as we access account not user)
        $this->request('GET', '/profile/privateuser');
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('privateuser', $json['username']);
        $this->assertAccountResponse($json);

        // Test accessing private profile (should work too)
        $this->request('GET', '/profile/privateuser', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('privateuser', $json['username']);
        $this->assertAccountResponse($json);
    }

    public function testRegisterNewAccount(): void
    {
        $uniqUsername = uniqid('UPPER');
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $uniqUsername,
                'password' => 'password',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals(mb_strtolower($uniqUsername), $json['username']);
        $this->assertUserCreateResponse($json);

        // Verify user can login with new credentials
        $token = $this->getToken(mb_strtolower($uniqUsername), 'password');
        $this->assertNotEmpty($token);

        $token = $this->getToken($uniqUsername, 'password');
        $this->assertNotEmpty($token);

        // Test registration with duplicate username (should fail)
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $uniqUsername,
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Test registration with roles specified (should ignore roles)
        $uniqUsernameWithRoles = uniqid('user');
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $uniqUsernameWithRoles,
                'password' => 'password',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertUserCreateResponse($json);
        $this->assertArrayNotHasKey('roles', $json, 'roles should not be in response');

        /** @var UserRepository $userRepository */
        $userRepository = $this->getUserRepository();
        $createdUser = $userRepository->findOneBy([], ['id' => 'DESC']);
        $this->assertNotNull($createdUser, 'User should be created.');
        $this->assertEquals($uniqUsernameWithRoles, $createdUser->username);

        // Verify user does not have the specified roles (should only have ROLE_USER)
        $userRoles = $createdUser->getRoles();
        $this->assertContains('ROLE_USER', $userRoles, 'User should have ROLE_USER.');
        $this->assertNotContains('ROLE_ADMIN', $userRoles, 'User should not have ROLE_ADMIN even if specified in registration.');
        $this->assertCount(1, $userRoles, 'User should only have ROLE_USER, no additional roles.');
    }

    public function testUsernameLengthValidation(): void
    {
        // Test registration with username too short (< 3 chars)
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => 'ab',
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 2 characters should be rejected.');

        // Test registration with username too long (> 32 chars)
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => str_repeat('a', 33),
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 33 characters should be rejected.');

        // Test registration with username at minimum length (3 chars) - should succeed
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => 'abc',
                'password' => 'password',
            ],
        ]);
        $this->assertResponseIsSuccessful('Username with 3 characters should be accepted.');
        $json = $this->getResponseArray();
        $this->assertEquals('abc', $json['username']);
        $this->assertUserCreateResponse($json);

        // Test updating profile with username too short
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'username' => 'ab',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Updating username to 2 characters should be rejected.');

        // Test updating profile with username too long
        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'username' => str_repeat('a', 33),
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Updating username to 33 characters should be rejected.');
    }

    public function testCreateUserWithMeta(): void
    {
        $uniqUsername = uniqid('user');
        $this->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $uniqUsername,
                'password' => 'password',
                'meta' => [
                    'theme' => 'dark',
                    'language' => 'en',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals($uniqUsername, $json['username']);
        $this->assertIsArray($json['meta']);
        $this->assertEquals('dark', $json['meta']['theme']);
        $this->assertEquals('en', $json['meta']['language']);
        $this->assertUserCreateResponse($json);
    }

    public function testUpdateUserWithMeta(): void
    {
        [, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'meta' => [
                    'theme' => 'light',
                    'notifications' => 'enabled',
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('testuser', $json['username']);
        $this->assertIsArray($json['meta']);
        $this->assertEquals('light', $json['meta']['theme']);
        $this->assertEquals('enabled', $json['meta']['notifications']);
        $this->assertUserOwnerResponse($json);
    }

    public function testUpdateUserMetaMergesNotOverwrites(): void
    {
        [$user, $token] = $this->createAuthenticatedUserAccount('testuser', 'test');

        $user->meta = [
            'theme' => 'dark',
            'language' => 'en',
            'timezone' => 'UTC',
        ];
        $manager = $this->container->get('doctrine')->getManager();
        $manager->flush();

        $this->request('PATCH', '/users/me', [
            'headers' => ['Content-Type' => 'application/json'],
            'auth_bearer' => $token,
            'json' => [
                'meta' => [
                    'theme' => 'light', // Update existing key
                    'notifications' => 'enabled', // Add new key
                ],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('testuser', $json['username']);
        $this->assertIsArray($json['meta']);
        // Updated value
        $this->assertEquals('light', $json['meta']['theme']);
        // New key added
        $this->assertEquals('enabled', $json['meta']['notifications']);
        // Existing keys preserved
        $this->assertEquals('en', $json['meta']['language']);
        $this->assertEquals('UTC', $json['meta']['timezone']);
        $this->assertUserOwnerResponse($json);
    }

    public function testGetPublicProfileWithHtmlAcceptRedirects(): void
    {
        $user = UserFactory::createOne(['username' => 'testuser', 'isPublic' => true]);
        AccountFactory::createOne(['username' => 'testuser', 'owner' => $user]);

        $this->request('GET', '/profile/testuser', [
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

    private function getUserRepository()
    {
        return $this->container->get('doctrine')->getManager()->getRepository(User::class);
    }

    /**
     * Asserts that a user response contains exactly the fields for user:show:private group.
     */
    private function assertUserOwnerResponse(array $json): void
    {
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);
        $this->assertIsArray($json['meta']);

        $userFields = array_keys($json);
        $expectedUserFields = ['username', 'isPublic', 'meta', 'account', '@iri'];
        $this->assertEqualsCanonicalizing(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that a user response contains exactly the fields for account:show:public group.
     */
    private function assertAccountResponse(array $json): void
    {
        $userFields = array_keys($json);
        $expectedUserFields = ['username', '@iri', 'instance', 'inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'];
        $this->assertEqualsCanonicalizing(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');

        // URL fields are nullable, but if present should be valid URLs
        foreach (['inboxUrl', 'outboxUrl', 'sharedInboxUrl', 'followerUrl', 'followingUrl'] as $urlField) {
            if (null !== $json[$urlField]) {
                $this->assertValidUrl($json[$urlField], "{$urlField} should be a valid URL if present");
            }
        }
    }

    /**
     * Asserts that a user response contains exactly the fields for user:create group.
     */
    private function assertUserCreateResponse(array $json): void
    {
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);
        $this->assertIsArray($json['meta']);

        $userFields = array_keys($json);
        $expectedUserFields = ['username', 'isPublic', 'meta', 'account', '@iri'];
        sort($expectedUserFields);
        $actualFields = array_values($userFields);
        sort($actualFields);
        $this->assertEquals(
            $expectedUserFields,
            $actualFields,
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );

        $this->assertArrayNotHasKey('password', $json, 'password should not be in response');

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }
}
