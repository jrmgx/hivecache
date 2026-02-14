<?php

namespace App\Api\Response;

use App\ActivityPub\Dto\OrderedCollection;
use App\ActivityPub\Dto\OrderedCollectionPage;
use App\ActivityPub\Dto\PersonActor;
use App\ActivityPub\Dto\PersonActorEndpoints;
use App\ActivityPub\Dto\PersonActorPublicKey;
use App\ActivityPub\Dto\WebFinger;
use App\ActivityPub\Dto\WebFingerLink;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\UrlGenerator;
use App\Entity\Account;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class ActivityPubResponseBuilder
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
        private SerializerInterface $serializer,
    ) {
    }

    // TODO
    public function todo(): JsonResponse
    {
        return $this->jsonActivity();
    }

    public function ok(): JsonResponse
    {
        return $this->jsonActivity();
    }

    public function profile(Account $account): JsonResponse
    {
        $person = new PersonActor();

        $person->id = $account->uri;
        $person->name = $account->username;
        $person->preferredUsername = $account->username;
        $person->inbox = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::Inbox,
            ['username' => $account->username]
        );
        $person->outbox = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::Outbox,
            ['username' => $account->username]
        );
        $person->url = $account->uri;
        $person->published = $account->createdAt->format(\DATE_ATOM);
        $person->following = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::Following,
            ['username' => $account->username]
        );
        $person->followers = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::Follower,
            ['username' => $account->username]
        );
        $person->publicKey = new PersonActorPublicKey();
        $person->publicKey->owner = $account->uri;
        $person->publicKey->id = $account->uri . '#main-key';
        $person->publicKey->publicKeyPem = $account->publicKey
            ?? throw new \RuntimeException('Missing publicKey for account.');
        $person->endpoints = new PersonActorEndpoints();
        $person->endpoints->sharedInbox = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::SharedInbox
        );

        return $this->jsonActivity($this->serializer->serialize($person, 'json'));
    }

    public function webfinger(string $username): JsonResponse
    {
        $profileUrl = $this->urlGenerator->generate(
            RouteType::Profile, RouteAction::Get,
            ['username' => $username]
        );

        $webfinger = new WebFinger();
        $webfinger->subject = "acct:{$username}@{$this->instanceHost}";
        $webfinger->aliases = [$profileUrl];
        $webfingerLink = new WebFingerLink();
        /* @noinspection HttpUrlsUsage */
        $webfingerLink->rel = 'http://webfinger.net/rel/profile-page';
        $webfingerLink->type = 'text/html';
        $webfingerLink->href = $profileUrl;
        $webfinger->links[] = $webfingerLink;
        $webfingerLink = new WebFingerLink();
        $webfingerLink->rel = 'self';
        $webfingerLink->type = 'application/activity+json';
        $webfingerLink->href = $profileUrl;
        $webfinger->links[] = $webfingerLink;

        return $this->jsonJrd($this->serializer->serialize($webfinger, 'json'));
    }

    public function orderedCollection(
        RouteAction $routeAction,
        Account $account,
        int $totalItems,
    ): JsonResponse {
        $collectionId = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            $routeAction,
            ['username' => $account->username]
        );

        $firstUrl = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            $routeAction,
            ['username' => $account->username, 'after' => OrderedCollection::FIRST_KEY]
        );

        $collection = new OrderedCollection();
        $collection->id = $collectionId;
        $collection->totalItems = $totalItems;
        $collection->first = $firstUrl;

        return $this->jsonActivity($this->serializer->serialize($collection, 'json'));
    }

    /**
     * @param array<string> $accountUris
     */
    public function orderedCollectionPage(
        RouteAction $routeAction,
        Account $account,
        array $accountUris,
        int $totalItems,
        ?string $after = null,
        ?string $nextPageUrl = null,
    ): JsonResponse {
        [$pageUrl, $collectionUrl] = $this->getCollectionBaseUrls($routeAction, $account, $after);

        $page = new OrderedCollectionPage();
        $page->id = $pageUrl;
        $page->totalItems = $totalItems;
        $page->next = $nextPageUrl;
        $page->partOf = $collectionUrl;
        $page->orderedItems = $accountUris;

        return $this->jsonActivity($this->serializer->serialize($page, 'json'));
    }

    /**
     * @param array<object> $activities
     */
    public function orderedCollectionPageWithActivities(
        RouteAction $routeAction,
        Account $account,
        array $activities,
        int $totalItems,
        ?string $after = null,
        ?string $nextPageUrl = null,
    ): JsonResponse {
        [$pageUrl, $collectionUrl] = $this->getCollectionBaseUrls($routeAction, $account, $after);

        $serializedActivities = [];
        foreach ($activities as $activity) {
            $serializedActivities[] = json_decode($this->serializer->serialize($activity, 'json'), true);
        }

        $page = new OrderedCollectionPage();
        $page->id = $pageUrl;
        $page->totalItems = $totalItems;
        $page->next = $nextPageUrl;
        $page->partOf = $collectionUrl;
        $page->orderedItems = $serializedActivities;

        return $this->jsonActivity($this->serializer->serialize($page, 'json'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getCollectionBaseUrls(RouteAction $routeAction, Account $account, ?string $after): array
    {
        $collectionUrl = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            $routeAction,
            ['username' => $account->username]
        );

        $pageUrl = $collectionUrl;
        if ($after) {
            if (OrderedCollection::FIRST_VALUE === $after) {
                $after = OrderedCollection::FIRST_KEY;
            }
            $pageUrl = $this->urlGenerator->generate(
                RouteType::ActivityPub,
                $routeAction,
                ['username' => $account->username, 'after' => $after]
            );
        }

        return [$pageUrl, $collectionUrl];
    }

    private function jsonActivity(mixed $data = null): JsonResponse
    {
        $response = new JsonResponse($data, json: null !== $data)->setEncodingOptions(\JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Type', 'application/activity+json');

        return $response;
    }

    private function jsonJrd(mixed $data = null): JsonResponse
    {
        $response = new JsonResponse($data, json: null !== $data)->setEncodingOptions(\JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Type', 'application/jrd+json; charset=utf-8');

        return $response;
    }
}
