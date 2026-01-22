<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\FollowActivity;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Following;
use App\Service\UrlGenerator;

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
