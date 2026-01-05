<?php

namespace App\Enum;

enum BookmarkIndexActionType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Outdated = 'outdated';
}
