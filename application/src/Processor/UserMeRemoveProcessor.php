<?php

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<User, void>
 */
final readonly class UserMeRemoveProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<User, void> $processor
     */
    public function __construct(
        #[Autowire('@api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $processor,
        private Security $security,
    ) {
    }

    /**
     * @param User $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser() ??
            throw new \LogicException('User must be defined on this endpoint.');

        $this->processor->process($user, $operation, $uriVariables, $context);
    }
}
