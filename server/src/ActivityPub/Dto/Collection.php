<?php

namespace App\ActivityPub\Dto;

final class Collection
{
    public string $type = 'Collection';
    public string $id;
    public CollectionPage $first;
}
