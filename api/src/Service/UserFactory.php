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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class UserFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
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
    public function new(string $username, string $password, bool $isPublic = false, array $meta = [])
    {
        $user = new User();
        $user->username = $username;
        $user->isPublic = $isPublic;
        $user->meta = $meta;
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);

        $key = $this->keysGenerator->generate();
        $account = new Account();
        $account->publicKey = (string) $key->getPublicKey();
        $account->privateKey = (string) $key;
        $account->username = $user->username;
        $account->instance = $this->instanceHost;
        $account->uri = $this->urlGenerator->generate(RouteType::Profile->value . RouteAction::Get->value, [
            'username' => $user->username,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->entityManager->persist($account);

        $user->account = $account;

        return [$user, $account];
    }
}
