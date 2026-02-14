<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveFollowMessageHandler;

/**
 * @see ReceiveFollowMessageHandler
 */
final readonly class ReceiveFollowMessage
{
    public function __construct(
        public string $payload,
    ) {
    }
}
