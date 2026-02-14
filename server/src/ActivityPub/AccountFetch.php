<?php

namespace App\ActivityPub;

use App\ActivityPub\Dto\PersonActor;
use App\ActivityPub\Dto\WebFinger;
use App\ActivityPub\Exception\WebFingerFetchException;
use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AccountFetch
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
        private AccountRepository $accountRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('@activity_pub.client')]
        private HttpClientInterface $httpClient,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @param string $usernameWithInstance even if the variable name has `withHost` it is not mandatory
     *
     * @return array{0: string, 1: string} Username and Host
     */
    public function parseUsernameWithInstance(string $usernameWithInstance): array
    {
        if (mb_substr_count(mb_ltrim($usernameWithInstance, '@'), '@') >= 2) {
            throw new BadRequestHttpException();
        }

        if (!preg_match('`' . Account::ACCOUNT_REGEX . '`', $usernameWithInstance, $matches)) {
            throw new BadRequestHttpException();
        }

        return [
            $matches[1],
            $matches[2] ?? $this->instanceHost,
        ];
    }

    public function fetchFromUri(string $uri): Account
    {
        $account = $this->accountRepository->findOneByUri($uri);
        if (!$account) {
            $personActor = $this->fetchProfileFromUri($uri);
            $account = $this->personActorToAccount($personActor);

            $this->entityManager->persist($account);
            $this->entityManager->flush();
        }

        return $account;
    }

    public function fetchFromUriOrNull(string $uri): ?Account
    {
        return $this->accountRepository->findOneByUri($uri);
    }

    /**
     * @param string $usernameWithInstance even if the variable name has `withHost` it is not mandatory
     */
    public function fetchFromUsernameInstance(string $usernameWithInstance): Account
    {
        [$username, $instance] = $this->parseUsernameWithInstance($usernameWithInstance);
        $account = $this->accountRepository->findOneByUsernameAndInstance($username, $instance);
        if (!$account) {
            $personActor = $this->fetchProfileFromUsernameAndInstance($username, $instance);
            $account = $this->personActorToAccount($personActor);
            $this->entityManager->persist($account);
            $this->entityManager->flush();
        }

        return $account;
    }

    private function fetchProfileFromUri(string $uri): PersonActor
    {
        try {
            $response = $this->httpClient->request('GET', $uri, [
                'headers' => ['Accept' => 'application/activity+json'],
            ]);

            return $this->serializer->deserialize($response->getContent(), PersonActor::class, 'json');
        } catch (\Exception $e) {
            throw new WebFingerFetchException('Error when fetching Profile.', previous: $e);
        }
    }

    private function fetchProfileFromUsernameAndInstance(string $username, string $instance): PersonActor
    {
        $webFinger = $this->fetchWebFinger($username, $instance);
        foreach ($webFinger->links as $link) {
            if ('self' === $link->rel && 'application/activity+json' === $link->type) {
                try {
                    return $this->fetchProfileFromUri($link->href);
                } catch (\Exception $e) {
                    throw new WebFingerFetchException('Error when fetching Profile.', previous: $e);
                }
            }
        }

        throw new WebFingerFetchException('Error when fetching Profile.');
    }

    private function fetchWebFinger(string $username, string $instance): WebFinger
    {
        try {
            $url = "https://{$instance}/.well-known/webfinger?resource=acct:{$username}@{$instance}";
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/activity+json',
                ],
            ]);

            return $this->serializer->deserialize($response->getContent(), WebFinger::class, 'json');
        } catch (\Exception $e) {
            throw new WebFingerFetchException('Error when fetching WebFinger.', previous: $e);
        }
    }

    private function personActorToAccount(PersonActor $personActor): Account
    {
        $parseUrl = parse_url($personActor->id);
        if (!$parseUrl) {
            throw new \LogicException('PersonActor->id is not a valid url.');
        }

        $host = $parseUrl['host']
            ?? throw new \LogicException('PersonActor->id has not a valid host.');
        $port = $parseUrl['port'] ?? null;

        $account = new Account();
        $account->username = $personActor->name;
        $account->instance = $host . ($port ? ':' . $port : '');
        $account->uri = $personActor->id;
        $account->inboxUrl = $personActor->inbox;
        $account->outboxUrl = $personActor->outbox;
        $account->sharedInboxUrl = $personActor->endpoints->sharedInbox;
        $account->followerUrl = $personActor->followers;
        $account->followingUrl = $personActor->following;
        $account->publicKey = $personActor->publicKey->publicKeyPem;

        return $account;
    }
}
