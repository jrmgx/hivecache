<?php

namespace App\ActivityPub\Message;

use App\ActivityPub\MessageHandler\SendDeleteTombstoneMessageHandler;

/**
 * @see SendDeleteTombstoneMessageHandler
 */
final readonly class SendDeleteTombstoneMessage
{
    /**
     * @param array<int, string> $sharedInboxUrls
     */
    public function __construct(
        public string $bookmarkUri,
        public string $actorUri,
        public string $bookmarkUrl,
        public array $sharedInboxUrls,
    ) {
    }
}
