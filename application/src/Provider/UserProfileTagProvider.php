<?php

namespace App\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Tag>
 */
// TODO this is quite similar to UserProfileBookmarkProvider merge in some way?
final readonly class UserProfileTagProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private TagRepository $tagRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return array<int, Tag>|Tag|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Tag|null
    {
        $username = $uriVariables['username'] ?? throw new \LogicException('No username in uri.');
        // TODO make user repository
        $profile = $this->userRepository->findOneBy(['username' => $username]);

        if (!$profile instanceof User) {
            return null;
        }

        $user = $this->security->getUser();
        $isOwner = $user?->getUserIdentifier() === $profile->getUserIdentifier();

        if ($operation instanceof CollectionOperationInterface) {
            // TODO move this to repository and handle filter + pagination
            if ($isOwner) {
                return $this->tagRepository->findBy(['owner' => $profile]);
            }

            return $this->tagRepository->findBy(['owner' => $profile, 'isPublic' => true]);
        }

        // TODO not sure if it need to be specific on the operation here
        // TODO it seems that on the opposite it must not to allow other operations
        // if ($operation instanceof Get) {
        // TODO move to repository
        $slug = $uriVariables['slug'] ?? throw new \LogicException('No slug found in uri.');

        if ($isOwner) {
            return $this->tagRepository->findOneBy(['owner' => $profile, 'slug' => $slug]);
        }

        return $this->tagRepository->findOneBy(['owner' => $profile, 'slug' => $slug, 'isPublic' => true]);
        // }
    }
}
