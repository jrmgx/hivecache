<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendFollowMessageHandler;

/**
 * @see SendFollowMessageHandler
 */
final readonly class SendFollowMessage
{
    public function __construct(
        public string $followingId,
    ) {
    }
}
