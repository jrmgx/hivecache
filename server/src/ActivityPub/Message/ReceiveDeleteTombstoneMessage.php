<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveDeleteTombstoneMessageHandler;

/**
 * @see ReceiveDeleteTombstoneMessageHandler
 */
final readonly class ReceiveDeleteTombstoneMessage
{
    public function __construct(
        public string $payload,
    ) {
    }
}
