<?php

namespace App\ActivityPub\Dto;

/**
 * @see Collection.json
 */
final class CollectionPage
{
    public string $type = 'CollectionPage';
    public ?string $next = null;
    public string $partOf;
    /** @var array<mixed> */
    public array $items = [];
}
