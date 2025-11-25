<?php

namespace App\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Bookmark;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Bookmark>
 */
final readonly class UserProfileBookmarkProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private BookmarkRepository $bookmarkRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @return array<int, Bookmark>|Bookmark|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Bookmark|null
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
            $tags = [];
            $tagsFilter = $operation->getParameters()?->get('tags')?->getValue();
            if ($tagsFilter && !($tagsFilter instanceof ParameterNotFound)) {
                $tags = array_filter(array_map('trim', explode(',', $tagsFilter)));
            }

            // TODO handle pagination
            if ($isOwner) {
                return $this->bookmarkRepository->applyTagFilter(
                    $this->bookmarkRepository->findByOwner($profile, onlyPublic: false), $tags, onlyPublic: true
                )
                    ->getQuery()
                    ->getResult()
                ;
            }

            // TODO handle pagination
            $bookmarks = $this->bookmarkRepository->applyTagFilter(
                $this->bookmarkRepository->findByOwner($profile, onlyPublic: true), $tags, onlyPublic: true
            )
                ->getQuery()
                ->getResult()
            ;

            foreach ($bookmarks as $bookmark) {
                $this->filterOutPrivateTags($bookmark);
            }

            return $bookmarks;
        }

        // TODO not sure if it need to be specific on the operation here
        // TODO it seems that on the opposite it must not to allow other operations
        // if ($operation instanceof Get) {
        // TODO move to repository
        $id = $uriVariables['id'] ?? throw new \LogicException('No id found in uri.');

        if ($isOwner) {
            return $this->bookmarkRepository->findOneByOwnerAndId($profile, $id, onlyPublic: false)
                ->getQuery()->getOneOrNullResult()
            ;
        }

        $bookmark = $this->bookmarkRepository->findOneByOwnerAndId($profile, $id, onlyPublic: true)
            ->getQuery()->getOneOrNullResult()
        ;
        $this->filterOutPrivateTags($bookmark);

        return $bookmark;

        // }
    }

    private function filterOutPrivateTags(?Bookmark $bookmark): void
    {
        if (!$bookmark) {
            return;
        }

        if ($bookmark->tags instanceof Collection) {
            $bookmark->tags = $bookmark->tags->filter(fn (Tag $tag) => $tag->isPublic);
        } else {
            throw new \LogicException('The bookmark.tags property is not a collection.');
        }
    }
}
