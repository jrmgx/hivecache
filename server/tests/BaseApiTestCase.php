<?php

namespace App\Tests;

use App\Api\UserFactory;
use App\Entity\Account;
use App\Entity\User;
use App\Factory\AccountFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

abstract class BaseApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected object $container;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->container = self::getContainer();
        $this->entityManager = $this->container->get('doctrine')->getManager();
    }

    protected function dump($vars): mixed
    {
        return $vars;

        return dump($vars);
    }

    /**
     * @return array{0: User, 1: Account}
     */
    protected function createUserAccountWithPassword(string $username, string $password): array
    {
        /** @var UserFactory $userFactory */
        $userFactory = $this->container->get(UserFactory::class);

        [$user, $account] = $userFactory->new($username, $password);

        // We need to persist those again because in our factory we are not in the same entity manager
        $this->entityManager->persist($user);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return [$user, $account];
    }

    /**
     * @return array{0: User, 1: string, 2: Account}
     */
    protected function createAuthenticatedUserAccount(string $username, string $password): array
    {
        [$user, $account] = $this->createUserAccountWithPassword($username, $password);
        $token = $this->getToken($user->username, $password);

        return [$user, $token, $account];
    }

    protected function getToken(string $username, string $password): string
    {
        $this->client->request('POST', '/auth', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $username,
            'password' => $password,
        ]));

        $response = $this->client->getResponse();
        if (!$response->isSuccessful()) {
            throw new \RuntimeException('Authentication failed: ' . $response->getContent());
        }

        $json = json_decode($response->getContent(), true);
        if (!isset($json['token'])) {
            throw new \RuntimeException('Token not found in response: ' . json_encode($json));
        }

        return $json['token'];
    }

    protected function createExternalAccount(string $username, string $password)
    {
        $httpClient = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);
        $externalServerUrl = 'https://' . AccountFactory::TEST_AP_SERVER_INSTANCE;

        $registerResponse = $httpClient->request('POST', $externalServerUrl . '/register', [
            'headers' => [
                'accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => $username,
                'password' => $password,
            ],
            'timeout' => 5,
        ]);
        $this->assertEquals(200, $registerResponse->getStatusCode(), 'User registration should succeed');

        return $registerResponse->toArray()['account'];
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

        // $this->dump([$method, $uri, $parameters, $content]);
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

    protected function assertValidUrl(mixed $url, string $message = 'Value should be a valid URL'): void
    {
        $this->assertIsString($url, $message);
        $this->assertMatchesRegularExpression('#^https?://[^\s]+$#', $url, $message);
    }

    /**
     * Asserts that a bookmark collection contains exactly the fields for bookmark:show:{private|public} group.
     * Note: the only difference is `isPublic` field.
     */
    protected function assertBookmarkCollection(array $bookmarks, bool $private = true): void
    {
        foreach ($bookmarks as $bookmark) {
            $this->assertIsString($bookmark['id']);
            $this->assertIsString($bookmark['createdAt']);
            $this->assertIsString($bookmark['title']);
            $this->assertIsString($bookmark['url']);
            $this->assertArrayHasKey('domain', $bookmark);
            $this->assertIsString($bookmark['domain']);
            if ($private) {
                $this->assertIsBool($bookmark['isPublic']);
            }
            $this->assertArrayHasKey('account', $bookmark);
            $this->assertArrayHasKey('tags', $bookmark);
            $this->assertIsArray($bookmark['tags']);
            $this->assertArrayHasKey('instance', $bookmark);
            $this->assertIsString($bookmark['instance']);

            $bookmarkFields = array_keys($bookmark);
            $expectedBookmarkFields = ['id', 'createdAt', 'title', 'url', 'domain', 'account', 'tags', 'instance', '@iri'];
            if ($private) {
                $expectedBookmarkFields[] = 'isPublic';
            }

            // Archive and mainImage are optional, add them to expected fields if present
            if (isset($bookmark['archive'])) {
                $expectedBookmarkFields[] = 'archive';
                $this->assertIsArray($bookmark['archive'], 'archive should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['archive']);
                $this->assertValidUrl($bookmark['archive']['contentUrl'], 'archive contentUrl should be a valid URL');
            }
            if (isset($bookmark['mainImage'])) {
                $expectedBookmarkFields[] = 'mainImage';
                $this->assertIsArray($bookmark['mainImage'], 'mainImage should be an unfolded FileObject');
                $this->assertArrayHasKey('contentUrl', $bookmark['mainImage']);
                $this->assertValidUrl($bookmark['mainImage']['contentUrl'], 'mainImage contentUrl should be a valid URL');
            }

            $this->assertEqualsCanonicalizing(
                $expectedBookmarkFields,
                array_values($bookmarkFields),
                'Each bookmark in collection should contain exactly ' . implode(', ', $expectedBookmarkFields) . ' fields'
            );

            $this->assertArrayHasKey('@iri', $bookmark);
            $this->assertValidUrl($bookmark['@iri'], '@iri should be a valid URL');
        }
    }
}
