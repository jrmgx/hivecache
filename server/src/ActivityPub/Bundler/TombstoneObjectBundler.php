<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\TombstoneObject;

final readonly class TombstoneObjectBundler
{
    public function bundle(string $uri): TombstoneObject
    {
        $tombstone = new TombstoneObject();
        $tombstone->id = $uri;

        return $tombstone;
    }
}
