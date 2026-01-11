<?php

namespace App\Service;

use App\Entity\Bookmark;
use App\Entity\BookmarkIndexAction;
use App\Enum\BookmarkIndexActionType;
use App\Repository\BookmarkIndexActionRepository;

final readonly class IndexActionUpdater
{
    public function __construct(
        private BookmarkIndexActionRepository $bookmarkIndexActionRepository,
    ) {
    }

    public function update(Bookmark $bookmark, BookmarkIndexActionType $type): BookmarkIndexAction
    {
        if (!$bookmark->account->owner) {
            throw new \RuntimeException('When updating the index, the bookmark must have an owner.');
        }

        $indexAction = new BookmarkIndexAction();
        $indexAction->bookmark = $bookmark;
        $indexAction->owner = $bookmark->account->owner;
        $indexAction->type = $type;

        $this->bookmarkIndexActionRepository->deleteOlderThan('30 days');

        return $indexAction;
    }
}
