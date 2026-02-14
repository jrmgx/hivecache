<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @see AcceptFollowActivity.json
 */
final class AcceptFollowActivity
{
    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'Accept';
    public string $id;
    public string $actor;
    public FollowActivity $object;
}
