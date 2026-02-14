<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\AcceptFollowActivity;
use App\ActivityPub\Dto\FollowActivity;
use App\Entity\Follower;

final readonly class AcceptFollowActivityBundler
{
    public function bundleFromFollowerAndFollow(Follower $follower, FollowActivity $followActivity): AcceptFollowActivity
    {
        $accept = new AcceptFollowActivity();
        $accept->id = $followActivity->id . '/accept';
        $accept->actor = $follower->owner->account->uri;
        $accept->object = $followActivity;

        return $accept;
    }
}
