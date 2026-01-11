<?php

namespace App\Enum;

enum FollowStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
}
