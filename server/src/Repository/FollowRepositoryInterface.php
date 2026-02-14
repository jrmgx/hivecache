<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;

interface FollowRepositoryInterface
{
    public function findByOwner(User $owner): QueryBuilder;
}
