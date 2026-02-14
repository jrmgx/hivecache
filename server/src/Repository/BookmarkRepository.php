<?php

/** @noinspection PhpUnused */

namespace App\Repository;

use App\Api\Helper\UrlHelper;
use App\Entity\Account;
use App\Entity\Bookmark;
use App\Entity\InstanceTag;
use App\Entity\User;
use App\Entity\UserTimelineEntry;
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

    public function findByAccount(Account $account, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
            ->addOrderBy('o.id', 'DESC')
            ->andWhere('o.outdated = false')
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }

    public function findTimelineByOwner(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join(UserTimelineEntry::class, 'ute', 'WITH', 'ute.bookmark = o')
            ->andWhere('ute.owner = :owner')
            ->setParameter('owner', $owner)
            ->addOrderBy('ute.id', 'DESC')
        ;
    }

    public function findTimelineByInstanceTag(InstanceTag $instanceTag): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.instanceTags', 'it')
            ->andWhere('it = :instanceTag')
            ->setParameter('instanceTag', $instanceTag)
            ->addOrderBy('o.id', 'DESC')
        ;
    }

    public function findByThisInstance(string $instance): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.instance = :instance')
            ->setParameter('instance', $instance)
            ->addOrderBy('o.id', 'DESC')
            ->andWhere('o.outdated = false')
            ->andWhere('o.isPublic = true')
        ;
    }

    public function findByOtherInstance(string $instance): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.instance <> :instance')
            ->setParameter('instance', $instance)
            ->addOrderBy('o.id', 'DESC')
            ->andWhere('o.outdated = false')
            ->andWhere('o.isPublic = true')
        ;
    }

    public function findOneByAccountAndId(Account $account, string $id, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }

    /**
     * @param string $url The url will be normalized
     */
    public function findLastOneByAccountAndUrl(Account $account, string $url): QueryBuilder
    {
        $normalizedUrl = UrlHelper::normalize($url);

        return $this->createQueryBuilder('o')
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
            ->andWhere('o.normalizedUrl = :normalizedUrl')
            ->setParameter('normalizedUrl', $normalizedUrl)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1)
        ;
    }

    /**
     * @param string $url The url will be normalized
     */
    public function findOutdatedByAccountAndUrl(Account $account, string $url): QueryBuilder
    {
        $normalizedUrl = UrlHelper::normalize($url);

        return $this->createQueryBuilder('o')
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
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
    public function deleteByAccountAndUrl(Account $account, string $url): void
    {
        $normalizedUrl = UrlHelper::normalize($url);

        $this->createQueryBuilder('o')
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
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
            ->join('o.userTags', 't')
            ->andWhere('t.slug IN (:tagSlugs)')
            ->setParameter('tagSlugs', $tagSlugs)
            ->groupBy('o.id')
            ->having('COUNT(DISTINCT t.id) = :tagCount')
            ->setParameter('tagCount', \count($tagSlugs))
        ;

        return $onlyPublic ? $qb->andWhere('t.isPublic = true') : $qb;
    }
}
