<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class OrderedCollection
{
    public const string FIRST_KEY = 'first';
    public const string FIRST_VALUE = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'OrderedCollection';
    public string $id;
    public int $totalItems;
    public string $first;
}
