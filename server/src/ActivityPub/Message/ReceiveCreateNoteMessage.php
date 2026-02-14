<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveCreateNoteMessageHandler;

/**
 * @see ReceiveCreateNoteMessageHandler
 */
final readonly class ReceiveCreateNoteMessage
{
    public function __construct(
        public string $payload,
    ) {
    }
}
