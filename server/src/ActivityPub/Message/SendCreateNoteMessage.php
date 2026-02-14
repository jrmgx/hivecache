<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendCreateNoteMessageHandler;

/**
 * @see SendCreateNoteMessageHandler
 */
final readonly class SendCreateNoteMessage
{
    public function __construct(
        public string $bookmarkId,
    ) {
    }
}
