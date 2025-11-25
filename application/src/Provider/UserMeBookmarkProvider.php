<?php

namespace App\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Bookmark;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Bookmark>
 */
final readonly class UserMeBookmarkProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private BookmarkRepository $bookmarkRepository,
    ) {
    }

    /**
     * @return array<int, Bookmark>|Bookmark|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Bookmark|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('Current user not found. Authentication required.');
        }

        if ($operation instanceof CollectionOperationInterface) {
            // TODO handle filter + pagination
            return $this->bookmarkRepository->findByOwner($user, onlyPublic: false)
                ->getQuery()->getResult()
            ;
        }

        // TODO not sure if it need to be specific on the operation here
        // TODO it seems that on the opposite it must not to allow other operations
        // if ($operation instanceof Get) {
        $id = $uriVariables['id'] ?? throw new \LogicException('No id found in uri.');

        return $this->bookmarkRepository->findOneByOwnerAndId($user, $id, onlyPublic: false)
            ->getQuery()->getOneOrNullResult()
        ;
        // }
    }
}
