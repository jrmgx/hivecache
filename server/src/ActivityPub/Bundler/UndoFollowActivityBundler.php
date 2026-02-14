<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\UndoFollowActivity;
use App\Entity\Following;

final readonly class UndoFollowActivityBundler
{
    public function __construct(
        private FollowActivityBundler $followActivityBundler,
    ) {
    }

    public function bundleFromFollowing(Following $following): UndoFollowActivity
    {
        $follow = $this->followActivityBundler->bundleFromFollowing($following);

        $undo = new UndoFollowActivity();
        $undo->id = $follow->id . '/undo';
        $undo->actor = $follow->actor;
        $undo->object = $follow;

        return $undo;
    }
}
