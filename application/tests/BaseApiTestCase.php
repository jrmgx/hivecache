<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

abstract class BaseApiTestCase extends ApiTestCase
{
    use ReloadDatabaseTrait;

    protected Client $client;
    protected object $container;

    protected function setUp(): void
    {
        parent::setUp();
        static::$alwaysBootKernel = true;
        $this->client = self::createClient();
        $this->container = self::getContainer();
    }

    protected function createUserWithPassword(string $email, string $username, string $password): User
    {
        $user = new User();
        $user->email = $email;
        $user->username = $username;
        $user->setPassword(
            $this->container->get('security.user_password_hasher')->hashPassword($user, $password)
        );

        $manager = $this->container->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();

        return $user;
    }

    /**
     * @return array{0: User, 1: string}
     */
    protected function createAuthenticatedUser(string $email, string $username, string $password): array
    {
        $user = $this->createUserWithPassword($email, $username, $password);
        $token = $this->getToken($user->email, $password);

        return [$user, $token];
    }

    /**
     * @return array{0: User, 1: string}
     */
    protected function createAdminUser(string $email, string $username, string $password): array
    {
        $user = $this->createUserWithPassword($email, $username, $password);
        $user->setRoles(['ROLE_ADMIN']);
        $this->container->get('doctrine')->getManager()->flush();
        $token = $this->getToken($user->email, $password);

        return [$user, $token];
    }

    protected function getToken(string $email, string $password): string
    {
        $response = $this->client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        $json = $response->toArray();

        return $json['token'];
    }

    protected function assertUnauthorized(string $method, string $url, array $options = [], ?string $message = null): void
    {
        $this->client->request($method, $url, $options);
        $this->assertResponseStatusCodeSame(401, $message ?? '');
    }
}
