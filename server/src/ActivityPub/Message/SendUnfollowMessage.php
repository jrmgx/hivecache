<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendUnfollowMessageHandler;

/**
 * @see SendUnfollowMessageHandler
 */
final readonly class SendUnfollowMessage
{
    public function __construct(
        public string $followingId,
    ) {
    }
}
