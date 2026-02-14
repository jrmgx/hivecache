<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\DeleteTombstoneActivity;

final readonly class DeleteActivityBundler
{
    public function __construct(
        private TombstoneObjectBundler $tombstoneObjectBundler,
    ) {
    }

    public function bundle(
        string $actorUri,
        string $bookmarkUri,
        string $bookmarkUrl,
    ): DeleteTombstoneActivity {
        $tombstone = $this->tombstoneObjectBundler->bundle($bookmarkUri . '#' . $bookmarkUrl);

        $deleteActivity = new DeleteTombstoneActivity();
        $deleteActivity->id = $bookmarkUri . '#delete';
        $deleteActivity->actor = $actorUri;
        $deleteActivity->object = $tombstone;

        return $deleteActivity;
    }
}
