<?php

namespace App\Api\Message;

use App\Api\MessageHandler\SynchronizeBookmarksForUserTagMessageHandler;

/**
 * @see SynchronizeBookmarksForUserTagMessageHandler
 */
final readonly class SynchronizeBookmarksForUserTagMessage
{
    public function __construct(
        public string $userTagId,
    ) {
    }
}
