<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Dto\DeleteTombstoneActivity;
use App\ActivityPub\Message\ReceiveDeleteTombstoneMessage;
use App\Repository\BookmarkRepository;
use App\Repository\UserTimelineEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class ReceiveDeleteTombstoneMessageHandler
{
    public function __construct(
        private SerializerInterface $serializer,
        private BookmarkRepository $bookmarkRepository,
        private AccountFetch $accountFetch,
        private EntityManagerInterface $entityManager,
        private UserTimelineEntryRepository $userTimelineEntryRepository,
    ) {
    }

    public function __invoke(ReceiveDeleteTombstoneMessage $message): void
    {
        $deleteTombstoneActivity = $this->serializer->deserialize($message->payload, DeleteTombstoneActivity::class, 'json');
        // For now, we only handle Delete Tombstone type (for bookmarks)
        if ('Tombstone' !== $deleteTombstoneActivity->object->type) {
            throw new UnrecoverableMessageHandlingException('Not a Delete Tombstone type.');
        }

        // Find the bookmark matching the URI
        // We use the normalized URL and will delete all the bookmarks that match this actor and that normalized url.
        $bookmarkUri = $deleteTombstoneActivity->object->id;
        $bookmarkUrl = explode('#', $bookmarkUri, 2)[1] ??
            throw new UnrecoverableMessageHandlingException('The URI does not contain #url');
        $actor = $this->accountFetch->fetchFromUri($deleteTombstoneActivity->actor);

        foreach ($this->bookmarkRepository->findByAccountAndUrl($actor, $bookmarkUrl) as $bookmark) {
            $this->userTimelineEntryRepository->deleteByBookmark($bookmark);
            $this->entityManager->remove($bookmark);
        }

        $this->entityManager->flush();
    }
}
