<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class FollowActivity
{
    /* {
            "@context":"https://www.w3.org/ns/activitystreams",
            "id":"https://hivecache.test/ap/u/alice/following#019bc314-d1d9-7d5d-a545-6e07a3ba6d3c",
            "type":"Follow",
            "actor":"https://hivecache.test/profile/alice",
            "object":"https://activitypub.academy/users/bob"
    } */
    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'Follow';
    public string $id;
    public string $actor;
    public string $object;
}
