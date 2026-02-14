<?php

namespace App\ActivityPub\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class UndoFollowActivity
{
    #[SerializedName('@context')]
    public string $context = Constant::CONTEXT_URL;
    public string $type = 'Undo';
    public string $id;
    public string $actor;
    public FollowActivity $object;
}
