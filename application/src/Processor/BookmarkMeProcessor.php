<?php

namespace App\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Bookmark;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Bookmark, Bookmark>
 */
final readonly class BookmarkMeProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Bookmark, Bookmark> $processor
     */
    public function __construct(
        #[Autowire('@api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $processor,
        private Security $security,
    ) {
    }

    /**
     * @param Bookmark $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Bookmark
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('Current user not found. Authentication required.');
        }

        // Automatically set the owner to the current logged-in user
        $data->owner = $user;

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
