<?php

namespace App\Service;

use App\ActivityPub\KeysGenerator;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class UserFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UrlGenerator $urlGenerator,
        private KeysGenerator $keysGenerator,
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
    ) {
    }

    /**
     * @param array<string, string> $meta
     *
     * @return array{0: User, 1: Account}
     */
    public function new(string $username, string $password, bool $isPublic = false, array $meta = []): array
    {
        $user = new User();
        $user->username = $username;
        $user->isPublic = $isPublic;
        $user->meta = $meta;
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);

        $activityPubRoute = fn (RouteAction $action) => $this->urlGenerator->generate(
            RouteType::ActivityPub, $action,
            ['username' => $user->username],
        );

        $key = $this->keysGenerator->generate();
        $account = new Account();
        $account->username = $user->username;
        $account->instance = $this->instanceHost;
        $account->uri = $this->urlGenerator->generate(
            RouteType::Profile,
            RouteAction::Get,
            ['username' => $user->username],
        );
        $account->publicKey = $key['public'];
        $account->privateKey = $key['private'];
        $account->sharedInboxUrl = $this->urlGenerator->generate(
            RouteType::ActivityPub, RouteAction::SharedInbox,
        );
        $account->inboxUrl = $activityPubRoute(RouteAction::Inbox);
        $account->outboxUrl = $activityPubRoute(RouteAction::Outbox);
        $account->followerUrl = $activityPubRoute(RouteAction::Follower);
        $account->followingUrl = $activityPubRoute(RouteAction::Following);
        $this->entityManager->persist($account);

        $user->account = $account;

        return [$user, $account];
    }
}
