<?php

namespace App\ActivityPub\Dto;

class TombstoneObject
{
    public string $type = 'Tombstone';
    public string $id;
    public string $atomUri { get => $this->id; }
}
