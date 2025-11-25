<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

class AuthenticationTest extends ApiTestCase
{
    use ReloadDatabaseTrait;

    public function testLogin(): void
    {
        static::$alwaysBootKernel = true;
        $client = self::createClient();
        $container = self::getContainer();

        $user = new User();
        $user->email = 'test@example.com';
        $user->username = 'test';
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, 'test')
        );

        $manager = $container->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();

        // retrieve a token
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'test@example.com',
                'password' => 'test',
            ],
        ]);

        $json = $response->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $json);

        // test not authorized
        $client->request('GET', '/api/users/me/bookmarks');
        $this->assertResponseStatusCodeSame(401);

        // test authorized
        $client->request('GET', '/api/users/me/bookmarks', [
            'auth_bearer' => $json['token'],
        ]);
        $this->assertResponseIsSuccessful();
    }
}
