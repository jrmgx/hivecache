<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @see AcceptFollowActivity.json
 */
final class FollowActivity
{
    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'Follow';
    public string $id;
    public string $actor;
    public string $object;
}
