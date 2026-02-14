<?php

namespace App\Repository;

use App\Entity\InstanceTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InstanceTag>
 */
class InstanceTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstanceTag::class);
    }

    public function findBySlug(string $slug): ?InstanceTag
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
