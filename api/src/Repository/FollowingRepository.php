<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Following;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Following>
 */
class FollowingRepository extends ServiceEntityRepository implements FollowRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Following::class);
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

    public function findOneByOwnerAndAccount(User $owner, Account $account): ?Following
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.account = :account')
            ->setParameter('account', $account)
            ->getQuery()
            ->getOneOrNullResult()
            // TODO we may consider the follow status here
        ;
    }
}
