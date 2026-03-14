<?php

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\Note;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    public function findOneByBookmarkAndUser(Bookmark $bookmark, User $user): ?Note
    {
        return $this->findOneBy(['bookmark' => $bookmark, 'owner' => $user]);
    }

    public function findByOwner(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.owner = :owner')
            ->setParameter('owner', $owner)
            ->addOrderBy('o.id', 'DESC')
        ;
    }
}
