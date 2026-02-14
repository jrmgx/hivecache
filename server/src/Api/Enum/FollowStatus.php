<?php

namespace App\Api\Enum;

enum FollowStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
}
