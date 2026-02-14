<?php

namespace App\Repository;

use App\Entity\FileObject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FileObject>
 */
class FileObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileObject::class);
    }

    public function findOneByOwnerAndId(User $owner, string $id): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
        ;
    }
}
