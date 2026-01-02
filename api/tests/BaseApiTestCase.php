<?php

namespace App\Tests;

use App\Entity\User;
use Hautelook\AliceBundle\PhpUnit\ReloadDatabaseTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BaseApiTestCase extends WebTestCase
{
    use ReloadDatabaseTrait;

    protected KernelBrowser $client;
    protected object $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createClient();
        $this->container = self::getContainer();
    }

    protected function dump($vars): mixed
    {
        return $vars;

        return dump($vars);
    }

    protected function createUserWithPassword(string $username, string $password): User
    {
        $user = new User();
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
    protected function createAuthenticatedUser(string $username, string $password): array
    {
        $user = $this->createUserWithPassword($username, $password);
        $token = $this->getToken($user->username, $password);

        return [$user, $token];
    }

    protected function getToken(string $username, string $password): string
    {
        $this->client->request('POST', '/auth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $json = json_decode($this->client->getResponse()->getContent(), true);

        return $json['token'];
    }

    /**
     * Make an HTTP request with Symfony's native format.
     *
     * @param string               $method  HTTP method
     * @param string               $uri     URI
     * @param array<string, mixed> $options Options with keys: json, auth_bearer, headers, extra[files]
     */
    protected function request(string $method, string $uri, array $options = []): void
    {
        $parameters = [];
        $files = [];
        $server = [];
        $content = null;

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $key => $value) {
                if ('content-type' === mb_strtolower($key)) {
                    $server['CONTENT_TYPE'] = $value;
                } else {
                    $server['HTTP_' . mb_strtoupper(str_replace('-', '_', $key))] = $value;
                }
            }
        }

        if (isset($options['auth_bearer'])) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $options['auth_bearer'];
        }

        if (isset($options['json'])) {
            $content = json_encode($options['json']);
            if (!isset($server['CONTENT_TYPE'])) {
                $server['CONTENT_TYPE'] = 'application/json';
            }
        }

        if (isset($options['extra']['files'])) {
            $files = $options['extra']['files'];
            // Merge any JSON data as form parameters
            if (isset($options['json'])) {
                $parameters = $options['json'];
            }
        }

        $this->client->request($method, $uri, $parameters, $files, $server, $content);
    }

    /**
     * Get response content as array.
     */
    protected function getResponseArray(): array
    {
        $content = $this->client->getResponse()->getContent();
        if (empty($content)) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }

    protected function assertUnauthorized(string $method, string $url, array $options = [], ?string $message = null): void
    {
        $this->request($method, $url, $options);
        $this->assertResponseStatusCodeSame(401, $message ?? '');
    }

    /**
     * Asserts that a value is a valid URL.
     *
     * @param mixed  $url     The value to validate
     * @param string $message Optional custom assertion message
     */
    protected function assertValidUrl(mixed $url, string $message = 'Value should be a valid URL'): void
    {
        $this->assertIsString($url, $message);
        $this->assertNotFalse(filter_var($url, \FILTER_VALIDATE_URL), $message);
    }
}
