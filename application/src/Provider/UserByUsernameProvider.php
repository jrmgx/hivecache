<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;

/**
 * @implements ProviderInterface<User>
 */
final readonly class UserByUsernameProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        $username = $uriVariables['username'] ?? null;

        if (!$username) {
            return null;
        }

        // TODO implement in repository
        return $this->userRepository->findOneBy(['username' => $username]);
    }
}
