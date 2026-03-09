<?php

namespace App\Api;

use App\Entity\Bookmark;
use App\Entity\InstanceTag;
use App\Entity\UserTag;
use App\Repository\BookmarkRepository;
use App\Repository\InstanceTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

// TODO add tests
final readonly class InstanceTagService
{
    public function __construct(
        private InstanceTagRepository $instanceTagRepository,
        private BookmarkRepository $bookmarkRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreate(string $name): InstanceTag
    {
        $slug = self::slugger($name);
        $instanceTag = $this->instanceTagRepository->findBySlug($slug);
        if (!$instanceTag) {
            $instanceTag = new InstanceTag();
            $instanceTag->name = $name;
            $this->entityManager->persist($instanceTag);
        }

        return $instanceTag;
    }

    /**
     * Sync instanceTags from userTags: rebuilds the collection from scratch,
     * so both additions and removals of user tags are reflected.
     */
    public function synchronize(Bookmark $bookmark): void
    {
        $bookmark->instanceTags->clear();
        foreach ($bookmark->userTags as $userTag) {
            if (!$userTag->isPublic) {
                continue;
            }
            $bookmark->instanceTags->add($this->findOrCreate($userTag->name));
        }
    }

    /**
     * Re-sync instance tags on all bookmarks that have this user tag.
     * Call when a user tag's isPublic changes so instance tags stay in sync.
     * Processes in batches to avoid OOM with large bookmark counts.
     */
    public function synchronizeBookmarksForUserTag(UserTag $userTag): void
    {
        $ids = $this->bookmarkRepository->findIdsByUserTag($userTag);
        foreach (array_chunk($ids, 100) as $batchIds) {
            $bookmarks = $this->bookmarkRepository->createQueryBuilder('o')
                ->join('o.userTags', 'ut')
                ->addSelect('ut')
                ->andWhere('o.id IN (:ids)')
                ->setParameter('ids', $batchIds)
                ->getQuery()
                ->getResult()
            ;
            foreach ($bookmarks as $bookmark) {
                $this->synchronize($bookmark);
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    public static function slugger(string $name): string
    {
        $slugger = new AsciiSlugger('en');

        return mb_strtolower($slugger->slug($name));
    }
}
