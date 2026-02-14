<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveAcceptMessageHandler;

/**
 * @see ReceiveAcceptMessageHandler
 */
final readonly class ReceiveAcceptMessage
{
    public function __construct(
        public string $payload,
    ) {
    }
}
