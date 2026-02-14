<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendAcceptMessageHandler;

/**
 * @see SendAcceptMessageHandler
 */
final readonly class SendAcceptMessage
{
    public function __construct(
        public string $followerId,
        public string $followPayload,
    ) {
    }
}
