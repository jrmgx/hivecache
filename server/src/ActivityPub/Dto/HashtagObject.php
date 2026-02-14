<?php

namespace App\ActivityPub\Dto;

/**
 * @see NoteObject.json
 */
final class HashtagObject
{
    public string $type = 'Hashtag';
    public string $href;
    public string $name;
}
