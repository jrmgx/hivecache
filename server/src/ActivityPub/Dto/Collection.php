<?php

namespace App\ActivityPub\Dto;

/**
 * @see Collection.json
 */
final class Collection
{
    public string $type = 'Collection';
    public string $id;
    public CollectionPage $first;
}
