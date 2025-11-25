<?php

namespace App\Tests;

use App\Entity\User;
use App\Factory\UserFactory;

class UserTest extends BaseApiTestCase
{
    public function testGetOwnProfile(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'test', 'test');

        $this->assertUnauthorized('GET', '/api/users/me');

        $response = $this->client->request('GET', '/api/users/me', ['auth_bearer' => $token]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('test@example.com', $json['email']);
        $this->assertEquals('test', $json['username']);
        $this->assertUserOwnerResponse($json);
    }

    public function testUpdateOwnProfile(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'test', 'test');

        $response = $this->client->request('PATCH', '/api/users/me', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'username' => 'updateduser',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('updateduser', $json['username']);
        $this->assertEquals('test@example.com', $json['email']);
        $this->assertUserOwnerResponse($json);

        $this->client->request('PATCH', '/api/users/me', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'plainPassword' => 'newpassword',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $newToken = $this->getToken('test@example.com', 'newpassword');
        $this->assertNotEmpty($newToken);
    }

    public function testDeleteOwnProfile(): void
    {
        [$user, $token] = $this->createAuthenticatedUser('test@example.com', 'test', 'test');

        $this->assertUnauthorized('DELETE', '/api/users/me', [], 'Deletion authorized but it should not.');

        $this->client->request('DELETE', '/api/users/me', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Verify user is deleted (from database)
        $userRepository = $this->getUserRepository();
        $deletedUser = $userRepository->find($user->id);
        $this->assertNull($deletedUser, 'User should be deleted from database.');

        // Verify user is deleted (API check)
        $this->client->request('GET', '/api/users/me', [
            'auth_bearer' => $token,
        ]);
        $this->assertResponseStatusCodeSame(401, 'User is deleted but still have access.');
    }

    public function testShowPublicProfile(): void
    {
        [, $token] = $this->createAuthenticatedUser('test@example.com', 'test', 'test');

        UserFactory::createOne([
            'email' => 'public@example.com',
            'username' => 'publicuser',
            'isPublic' => true,
        ]);

        UserFactory::createOne([
            'email' => 'private@example.com',
            'username' => 'privateuser',
            'isPublic' => false,
        ]);

        // Test accessing public profile via username (no JWT)
        $response = $this->client->request('GET', '/api/profile/publicuser');
        $this->assertResponseIsSuccessful();
        $json = $response->toArray();
        $this->assertEquals('publicuser', $json['username']);
        $this->assertUserProfileResponse($json);

        // Test accessing private profile (should fail)
        $this->client->request('GET', '/api/profile/privateuser');
        $this->assertResponseStatusCodeSame(401, 'Private user profile is accessible (no JWT).');

        // Test accessing private profile (should fail)
        $this->client->request('GET', '/api/profile/privateuser', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(403, 'Private user profile is accessible (with other JWT).');
    }

    public function testRegisterNewAccount(): void
    {
        $uniqUsername = uniqid('user_');
        $response = $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $uniqUsername . '@example.com',
                'username' => $uniqUsername,
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals($uniqUsername . '@example.com', $json['email']);
        $this->assertEquals($uniqUsername, $json['username']);
        $this->assertUserCreateResponse($json);

        // Verify user can login with new credentials
        $token = $this->getToken($uniqUsername . '@example.com', 'password');
        $this->assertNotEmpty($token);

        // Test registration with duplicate email (should fail)
        $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $uniqUsername . '@example.com',
                'username' => $uniqUsername . '_other',
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Test registration with duplicate username (should fail)
        $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $uniqUsername . '_other@example.com',
                'username' => $uniqUsername,
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);

        // Test registration with roles specified (should ignore roles)
        $uniqUsernameWithRoles = uniqid('user_');
        $response = $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $uniqUsernameWithRoles . '@example.com',
                'username' => $uniqUsernameWithRoles,
                'plainPassword' => 'password',
                'roles' => ['ROLE_ADMIN'],
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertUserCreateResponse($json);
        $this->assertArrayNotHasKey('roles', $json, 'roles should not be in response');

        $userRepository = $this->getUserRepository();
        $userId = $json['id'];
        $createdUser = $userRepository->find($userId);
        $this->assertNotNull($createdUser, 'User should be created.');

        // Verify user does not have the specified roles (should only have ROLE_USER)
        $userRoles = $createdUser->getRoles();
        $this->assertContains('ROLE_USER', $userRoles, 'User should have ROLE_USER.');
        $this->assertNotContains('ROLE_ADMIN', $userRoles, 'User should not have ROLE_ADMIN even if specified in registration.');
        $this->assertCount(1, $userRoles, 'User should only have ROLE_USER, no additional roles.');
    }

    public function testUsernameLengthValidation(): void
    {
        $baseEmail = uniqid('test_') . '@example.com';

        // Test registration with username too short (< 3 chars)
        $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $baseEmail,
                'username' => 'ab',
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 2 characters should be rejected.');

        // Test registration with username too long (> 32 chars)
        $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => uniqid('test_') . '@example.com',
                'username' => str_repeat('a', 33),
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Username with 33 characters should be rejected.');

        // Test registration with username at minimum length (3 chars) - should succeed
        $response = $this->client->request('POST', '/api/users', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => uniqid('test_') . '@example.com',
                'username' => 'abc',
                'plainPassword' => 'password',
            ],
        ]);
        $this->assertResponseIsSuccessful('Username with 3 characters should be accepted.');
        $json = $response->toArray();
        $this->assertEquals('abc', $json['username']);
        $this->assertUserCreateResponse($json);

        // Test updating profile with username too short
        [, $token] = $this->createAuthenticatedUser(uniqid('test_') . '@example.com', 'testuser', 'test');

        $this->client->request('PATCH', '/api/users/me', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'username' => 'ab',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Updating username to 2 characters should be rejected.');

        // Test updating profile with username too long
        $this->client->request('PATCH', '/api/users/me', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $token,
            'json' => [
                'username' => str_repeat('a', 33),
            ],
        ]);
        $this->assertResponseStatusCodeSame(422, 'Updating username to 33 characters should be rejected.');
    }

    public function testAdminListAllUsers(): void
    {
        [, $adminToken] = $this->createAdminUser('admin@example.com', 'admin', 'test');

        UserFactory::createMany(5);

        [, $regularToken] = $this->createAuthenticatedUser('regular@example.com', 'regular', 'test');
        $this->client->request('GET', '/api/users', ['auth_bearer' => $regularToken]);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->client->request('GET', '/api/users', ['auth_bearer' => $adminToken]);
        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        // admin + 5 users
        $this->assertGreaterThanOrEqual(6, \count($json['member']));
        $this->assertUserAdminCollection($json['member']);
    }

    public function testAdminEditUser(): void
    {
        [, $adminToken] = $this->createAdminUser('admin@example.com', 'admin', 'test');

        $user = UserFactory::createOne([
            'email' => 'user@example.com',
            'username' => 'regularuser',
        ]);

        [, $regularToken] = $this->createAuthenticatedUser('regular@example.com', 'regular', 'test');
        $this->client->request('PATCH', "/api/users/{$user->id}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $regularToken,
            'json' => [
                'username' => 'hacked',
            ],
        ]);
        $this->assertResponseStatusCodeSame(403);

        $response = $this->client->request('PATCH', "/api/users/{$user->id}", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'auth_bearer' => $adminToken,
            'json' => [
                'username' => 'updatedbyadmin',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $json = $response->toArray();

        $this->assertEquals('updatedbyadmin', $json['username']);
        $this->assertEquals('user@example.com', $json['email']);
        $this->assertUserAdminResponse($json);
    }

    public function testAdminDeleteUser(): void
    {
        [, $adminToken] = $this->createAdminUser('admin@example.com', 'admin', 'test');

        $user = UserFactory::createOne([
            'email' => 'user@example.com',
            'username' => 'regularuser',
        ]);
        $userId = $user->id;

        [, $regularToken] = $this->createAuthenticatedUser('regular@example.com', 'regular', 'test');
        $this->client->request('DELETE', "/api/users/{$userId}", ['auth_bearer' => $regularToken]);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('DELETE', "/api/users/{$userId}", ['auth_bearer' => $adminToken]);
        $this->assertResponseStatusCodeSame(204);

        // Verify user is deleted from database
        $userRepository = $this->getUserRepository();
        $deletedUser = $userRepository->find($userId);
        $this->assertNull($deletedUser, 'User should be deleted from database.');

        // Verify user is deleted (API check)
        $response = $this->client->request('GET', '/api/users', ['auth_bearer' => $adminToken]);
        $json = $response->toArray();
        $userIds = array_map(fn ($u) => $u['id'], $json['member']);
        $this->assertNotContains($userId, $userIds);
    }

    private function getUserRepository()
    {
        return $this->container->get('doctrine')->getManager()->getRepository(User::class);
    }

    /**
     * Asserts that a user response contains exactly the fields for user:owner group.
     */
    private function assertUserOwnerResponse(array $json): void
    {
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);

        $userFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedUserFields = ['id', 'email', 'username', 'isPublic'];
        $this->assertEquals(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );
    }

    /**
     * Asserts that a user response contains exactly the fields for user:profile group.
     */
    private function assertUserProfileResponse(array $json): void
    {
        $userFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedUserFields = ['username'];
        $this->assertEquals(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' field'
        );
    }

    /**
     * Asserts that a user response contains exactly the fields for user:create group.
     */
    private function assertUserCreateResponse(array $json): void
    {
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertIsBool($json['isPublic']);

        $userFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedUserFields = ['id', 'email', 'username', 'isPublic'];
        $this->assertEquals(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields (excluding JSON-LD metadata)'
        );

        $this->assertArrayNotHasKey('plainPassword', $json, 'plainPassword should not be in response');
    }

    /**
     * Asserts that a user response contains exactly the fields for user:admin group.
     */
    private function assertUserAdminResponse(array $json): void
    {
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('isPublic', $json);
        $this->assertArrayHasKey('roles', $json);
        $this->assertIsString($json['id']);
        $this->assertIsBool($json['isPublic']);
        $this->assertIsArray($json['roles']);

        $userFields = array_filter(array_keys($json), fn ($key) => !str_starts_with($key, '@'));
        $expectedUserFields = ['id', 'email', 'username', 'isPublic', 'roles'];
        $this->assertEquals(
            $expectedUserFields,
            array_values($userFields),
            'Response should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
        );

        $this->assertArrayNotHasKey('plainPassword', $json, 'plainPassword should not be in response');
    }

    /**
     * Asserts that each user in a collection contains exactly the fields for user:admin group.
     */
    private function assertUserAdminCollection(array $users): void
    {
        foreach ($users as $user) {
            $this->assertIsString($user['id']);
            $this->assertIsString($user['email']);
            $this->assertIsString($user['username']);
            $this->assertIsBool($user['isPublic']);
            $this->assertIsArray($user['roles']);

            $userFields = array_filter(array_keys($user), fn ($key) => !str_starts_with($key, '@'));
            $expectedUserFields = ['id', 'email', 'username', 'isPublic', 'roles'];
            $this->assertEquals(
                $expectedUserFields,
                array_values($userFields),
                'Each user in collection should contain exactly ' . implode(', ', $expectedUserFields) . ' fields'
            );

            $this->assertArrayNotHasKey('plainPassword', $user, 'plainPassword should not be in response');
        }
    }
}
