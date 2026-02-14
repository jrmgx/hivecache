<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\FollowActivity;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\UrlGenerator;
use App\Entity\Following;

final readonly class FollowActivityBundler
{
    public function __construct(
        private UrlGenerator $urlGenerator,
    ) {
    }

    public function bundleFromFollowing(Following $following): FollowActivity
    {
        $actorAccount = $following->owner->account;
        $objectAccount = $following->account;

        $follow = new FollowActivity();
        $follow->id = $this->urlGenerator->generate(
            RouteType::ActivityPub,
            RouteAction::Following,
            ['username' => $actorAccount->username]
        ) . '#' . $following->id;
        $follow->actor = $actorAccount->uri;
        $follow->object = $objectAccount->uri;

        return $follow;
    }
}
