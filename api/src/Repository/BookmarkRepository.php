<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\User;
use App\Helper\UrlHelper;
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

    public function findOneById(string $id): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
        ;
    }

    public function findByOwner(User $owner, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->addOrderBy('o.id', 'DESC')
            ->andWhere('o.outdated = false')
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }

    public function findOneByOwnerAndId(User $owner, string $id, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }

    /**
     * @param string $url The url will be normalized
     */
    public function findLastOneByOwnerAndUrl(User $owner, string $url): QueryBuilder
    {
        $normalizedUrl = UrlHelper::normalize($url);

        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.normalizedUrl = :normalizedUrl')
            ->setParameter('normalizedUrl', $normalizedUrl)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
        ;
    }

    /**
     * @param string $url The url will be normalized
     */
    public function findOutdatedByOwnerAndUrl(User $owner, string $url): QueryBuilder
    {
        $normalizedUrl = UrlHelper::normalize($url);

        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.normalizedUrl = :normalizedUrl')
            ->setParameter('normalizedUrl', $normalizedUrl)
            ->andWhere('o.outdated = :true')
            ->setParameter('true', true)
            ->orderBy('o.id', 'DESC')
        ;
    }

    /**
     * @param string $url The url will be normalized
     */
    public function deleteByOwnerAndUrl(User $owner, string $url): void
    {
        $normalizedUrl = UrlHelper::normalize($url);

        $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.normalizedUrl = :normalizedUrl')
            ->setParameter('normalizedUrl', $normalizedUrl)
            ->delete()
            ->getQuery()
            ->execute()
        ;
    }

    /**
     * Apply filters:
     * - tags: all given tags must be present and extra tags can be present
     * - search: fulltext search in title, url and description TODO
     *
     * @param array<string> $tagSlugs
     */
    public function applyFilters(QueryBuilder $qb, array $tagSlugs, bool $onlyPublic): QueryBuilder
    {
        if (0 === \count($tagSlugs)) {
            return $qb;
        }

        $qb = $qb
            ->join('o.tags', 't')
            ->andWhere('t.slug IN (:tagSlugs)')
            ->setParameter('tagSlugs', $tagSlugs)
            ->groupBy('o.id')
            ->having('COUNT(DISTINCT t.id) = :tagCount')
            ->setParameter('tagCount', \count($tagSlugs))
        ;

        return $onlyPublic ? $qb->andWhere('t.isPublic = true') : $qb;
    }

    public function applyPagination(QueryBuilder $qb, ?string $after, int $resultPerPage): QueryBuilder
    {
        if ($after) {
            $qb = $qb->andWhere('o.id < :after')
                ->setParameter('after', $after)
            ;
        }

        return $qb
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($resultPerPage)
        ;
    }
}
