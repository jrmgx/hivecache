<?php

namespace App\ActivityPub\Dto;

final class HashtagObject
{
    public string $type = 'Hashtag';
    public string $href;
    public string $name;
}
