<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveUnfollowMessageHandler;

/**
 * @see ReceiveUnfollowMessageHandler
 */
final readonly class ReceiveUnfollowMessage
{
    public function __construct(
        public string $payload,
    ) {
    }
}
