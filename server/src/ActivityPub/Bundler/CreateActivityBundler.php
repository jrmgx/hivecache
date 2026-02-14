<?php

namespace App\ActivityPub\Bundler;

use App\ActivityPub\Dto\CreateNoteActivity;
use App\Entity\Bookmark;
use App\Entity\Follower;

final readonly class CreateActivityBundler
{
    public function __construct(
        private NoteObjectBundler $noteObjectBundler,
    ) {
    }

    /**
     * @param array<int, Follower|string> $followers
     */
    public function bundleFromBookmark(Bookmark $bookmark, array $followers): CreateNoteActivity
    {
        $noteObject = $this->noteObjectBundler->bundleFromBookmark($bookmark, $followers);

        $createActivity = new CreateNoteActivity();
        $createActivity->id = $noteObject->id;
        $createActivity->actor = $bookmark->account->uri;
        $createActivity->published = $noteObject->published;
        $createActivity->cc = $noteObject->cc;
        $createActivity->object = $noteObject;

        return $createActivity;
    }
}
