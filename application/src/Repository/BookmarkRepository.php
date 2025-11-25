<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bookmark>
 */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bookmark::class);
    }

    public function findByOwner(User $owner, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->setParameter('owner', $owner)
        ;

        return $onlyPublic ? $qb->andWhere('b.isPublic = true') : $qb;
    }

    public function findOneByOwnerAndId(User $owner, string $uid, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('b.id = :id')
            ->setParameter('id', $uid)
        ;

        return $onlyPublic ? $qb->andWhere('b.isPublic = true') : $qb;
    }

    /**
     * Apply tags filter, where all given tags must be present and extra tags can be present.
     *
     * @param array<string> $tagNames
     */
    public function applyTagFilter(QueryBuilder $qb, array $tagNames, bool $onlyPublic): QueryBuilder
    {
        if (0 === \count($tagNames)) {
            return $qb;
        }

        $qb = $qb
            ->join('b.tags', 't')
            ->andWhere('t.name IN (:tagNames)')
            ->setParameter('tagNames', $tagNames)
            ->groupBy('b.id')
            ->having('COUNT(DISTINCT t.id) = :tagCount')
            ->setParameter('tagCount', \count($tagNames))
        ;

        return $onlyPublic ? $qb->andWhere('t.isPublic = true') : $qb;
    }
}
