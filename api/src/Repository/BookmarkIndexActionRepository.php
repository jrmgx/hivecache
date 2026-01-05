<?php

namespace App\Repository;

use App\Entity\BookmarkIndexAction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

/**
 * @extends ServiceEntityRepository<BookmarkIndexAction>
 */
class BookmarkIndexActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookmarkIndexAction::class);
    }

    public function findByOwner(User $owner, ?string $before): QueryBuilder
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
        ;

        if ($before) {
            $qb = $qb->andWhere('o.id > :before')
                ->setParameter('before', $before)
            ;
        }

        return $qb->addOrderBy('o.id', 'ASC');
    }

    public function deleteOlderThan(string $relativeDateString): void
    {
        $id = UuidV7::generate(new \DateTimeImmutable("-{$relativeDateString}"));
        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.id < :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute()
        ;
    }
}
