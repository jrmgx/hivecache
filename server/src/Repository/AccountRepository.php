<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findOneByUsernameAndInstance(string $username, string $instance): ?Account
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.username = :username')
            ->setParameter('username', $username)
            ->andWhere('o.instance = :instance')
            ->setParameter('instance', $instance)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByUri(string $uri): ?Account
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.uri = :uri')
            ->setParameter('uri', $uri)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
