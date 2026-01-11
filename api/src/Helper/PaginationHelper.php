<?php

namespace App\Helper;

use Doctrine\ORM\QueryBuilder;

class PaginationHelper
{
    public static function applyPagination(QueryBuilder $qb, ?string $after, int $resultPerPage): QueryBuilder
    {
        if ($after) {
            $qb = $qb->andWhere('o.id < :after')
                ->setParameter('after', $after)
            ;
        }

        return $qb
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($resultPerPage)
        ;
    }
}
