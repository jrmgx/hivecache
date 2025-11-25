<?php

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<User, User>
 */
final readonly class UserMeProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<User, User> $processor
     */
    public function __construct(
        #[Autowire('@api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
    ) {
    }

    /**
     * @param User $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        // Patch means we need to merge $data and $user
        if ($operation instanceof Patch) {
            /** @var User $user */
            $user = $this->security->getUser();
            $user->username = $data->username ?? $user->username;
            $user->email = $data->email ?? $user->email;
            $user->setPlainPassword($data->getPlainPassword());

            $data = $user;
        }

        // Password update
        if ($data->getPlainPassword()) {
            $data->setPassword($this->passwordHasher->hashPassword($data, $data->getPlainPassword()));
            $data->setPlainPassword(null);
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
