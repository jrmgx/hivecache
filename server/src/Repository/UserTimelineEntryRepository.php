<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\UserTimelineEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserTimelineEntry>
 */
class UserTimelineEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTimelineEntry::class);
    }

    public function deleteByBookmark(Bookmark $bookmark): void
    {
        $this->createQueryBuilder('o')
            ->andWhere('o.bookmark = :bookmark')
            ->setParameter('bookmark', $bookmark)
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }
}
