<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserTag>
 */
class UserTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTag::class);
    }

    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('count(o.id)')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function findByOwner(User $owner, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->addOrderBy('o.id', 'ASC')
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }

    public function findOneByOwnerAndSlug(User $owner, string $slug, bool $onlyPublic): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.slug = :slug')
            ->setParameter('slug', $slug)
        ;

        return $onlyPublic ? $qb->andWhere('o.isPublic = true') : $qb;
    }
}
