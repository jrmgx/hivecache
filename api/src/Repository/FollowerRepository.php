<?php

namespace App\Repository;

use App\Entity\Follower;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follower>
 */
class FollowerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follower::class);
    }

    public function findByOwner(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.account', 'a')
            ->select('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->addOrderBy('o.id', 'DESC')
            // TODO we may consider the follow status here
        ;
    }
}
