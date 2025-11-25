<?php

namespace App\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Tag>
 */
// TODO this is quite similar to UserMeBookmarkProvider. Merge in some way?
final readonly class UserMeTagProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * @return array<int, Tag>|Tag|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Tag|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('Current user not found. Authentication required.');
        }

        if ($operation instanceof CollectionOperationInterface) {
            // TODO move this to repository and handle filter + pagination
            return $this->tagRepository->findBy(['owner' => $user]);
        }

        // TODO not sure if it need to be specific on the operation here
        // TODO it seems that on the opposite it must not to allow other operations
        // if ($operation instanceof Get) {
        // TODO move to repository
        $slug = $uriVariables['slug'] ?? throw new \LogicException('No slug found in uri.');

        return $this->tagRepository->findOneBy(['owner' => $user, 'slug' => $slug]);
        // }
    }
}
