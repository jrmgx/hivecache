<?php

namespace App\Tests;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Repository\UserRepository;

class UserTest extends BaseApiTestCase
{
    public function testGetOwnProfile(): void
    {
        [, $token] = $this->createAuthenticatedUser('test', 'test');

        $this->assertUnauthorized('GET', '/users/me');

        $this->request('GET', '/users/me', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $this->getResponseArray();

        $this->assertEquals('test', $json['username']);
        $this->assertUserOwnerResponse($json);
    }

    public function testUpdateOwnProfile(): void
    {
        [, $originalToken] = $this->createAuthenticatedUser('test', 'test');

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
        [, $oldToken] = $this->createAuthenticatedUser('test', 'oldpassword');

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
        [$user, $token] = $this->createAuthenticatedUser('test', 'test');

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
        [, $token] = $this->createAuthenticatedUser('test', 'test');

        UserFactory::createOne([
            'username' => 'publicuser',
            'isPublic' => true,
        ]);

        UserFactory::createOne([
            'username' => 'privateuser',
            'isPublic' => false,
        ]);

        // Test accessing public profile via username (no JWT)
        $this->request('GET', '/profile/publicuser');
        $this->assertResponseIsSuccessful();
        $json = $this->getResponseArray();
        $this->assertEquals('publicuser', $json['username']);
        $this->assertUserProfileResponse($json);

        // Test accessing private profile (should fail)
        $this->request('GET', '/profile/privateuser');
        $this->assertResponseStatusCodeSame(404, 'Private user profile is accessible (no JWT).');

        // Test accessing private profile (should fail)
        $this->request('GET', '/profile/privateuser', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(404, 'Private user profile is accessible (with other JWT).');
    }

    public function testRegisterNewAccount(): void
    {
        $uniqUsername = uniqid('UPPER_');
        $this->request('POST', '/account', [
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
        $this->request('POST', '/account', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => $uniqUsername,
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Test registration with roles specified (should ignore roles)
        $uniqUsernameWithRoles = uniqid('user_');
        $this->request('POST', '/account', [
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
        $this->request('POST', '/account', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => 'ab',
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 2 characters should be rejected.');

        // Test registration with username too long (> 32 chars)
        $this->request('POST', '/account', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => str_repeat('a', 33),
                'password' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 33 characters should be rejected.');

        // Test registration with username at minimum length (3 chars) - should succeed
        $this->request('POST', '/account', [
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
        [, $token] = $this->createAuthenticatedUser('testuser', 'test');

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
        $uniqUsername = uniqid('user_');
        $this->request('POST', '/account', [
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
        [, $token] = $this->createAuthenticatedUser('testuser', 'test');

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
        [$user, $token] = $this->createAuthenticatedUser('testuser', 'test');

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
        $expectedUserFields = ['username', 'isPublic', 'meta', '@iri'];
        $this->assertEqualsCanonicalizing(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
    }

    /**
     * Asserts that a user response contains exactly the fields for user:show:public group.
     */
    private function assertUserProfileResponse(array $json): void
    {
        $userFields = array_keys($json);
        $expectedUserFields = ['username', '@iri'];
        $this->assertEquals(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' field'
        );

        $this->assertArrayHasKey('@iri', $json);
        $this->assertValidUrl($json['@iri'], '@iri should be a valid URL');
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
        $expectedUserFields = ['username', 'isPublic', 'meta', '@iri'];
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
