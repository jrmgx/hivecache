<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendMessageHandler;

/**
 * @see SendMessageHandler
 */
final readonly class SendMessage
{
    public function __construct(
        public string $payload,
        public string $url,
        public string $accountUri,
    ) {
    }
}
