<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\ReceiveInboxMessageHandler;

/**
 * @see ReceiveInboxMessageHandler
 */
final readonly class ReceiveInboxMessage implements SignedMessageInterface
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        public string $username, // TODO username does not seem to be needed
        public string $body,
        public string $host, // TODO host does not seem to be needed
        public string $method, // TODO method does not seem to be needed
        public string $uri,
        public array $headers,
    ) {
    }
}
