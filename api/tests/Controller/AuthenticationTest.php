<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\UserFactory;
use App\Tests\BaseApiTestCase;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;

class AuthenticationTest extends BaseApiTestCase
{
    use ReloadDatabaseTrait;

    public function testLogin(): void
    {
        $container = $this->container;

        $user = new User();
        $user->username = 'test';
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, 'test')
        );

        /** @var UserFactory $userFactory */
        $userFactory = $container->get(UserFactory::class);
        [$user, $account] = $userFactory->new('test', 'test');

        $manager = $container->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->persist($account);
        $manager->flush();

        // retrieve a token
        $this->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'username' => 'test',
                'password' => 'test',
            ],
        ]);

        $json = $this->dump($this->getResponseArray());
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $json);

        // test not authorized
        $this->request('GET', '/users/me/bookmarks');
        $this->assertResponseStatusCodeSame(401);

        // test authorized
        $this->request('GET', '/users/me/bookmarks', [
            'auth_bearer' => $json['token'],
        ]);
        $this->assertResponseIsSuccessful();
    }
}
