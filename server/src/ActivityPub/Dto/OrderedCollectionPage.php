<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class OrderedCollectionPage
{
    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'OrderedCollectionPage';
    public string $id;
    public int $totalItems;
    public ?string $next = null;
    public string $partOf;
    /** @var array<string|object> */
    public array $orderedItems = [];
}
