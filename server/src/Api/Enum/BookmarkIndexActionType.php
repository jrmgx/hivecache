<?php

namespace App\Api\Enum;

enum BookmarkIndexActionType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Outdated = 'outdated';
}
