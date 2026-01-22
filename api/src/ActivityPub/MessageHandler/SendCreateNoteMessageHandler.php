<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Bundler\CreateActivityBundler;
use App\ActivityPub\Message\SendCreateNoteMessage;
use App\ActivityPub\Message\SendMessage;
use App\Entity\Follower;
use App\Repository\BookmarkRepository;
use App\Repository\FollowerRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class SendCreateNoteMessageHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private BookmarkRepository $bookmarkRepository,
        private CreateActivityBundler $createActivityBundler,
        private FollowerRepository $followerRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendCreateNoteMessage $message): void
    {
        $bookmark = $this->bookmarkRepository->find($message->bookmarkId)
            ?? throw new UnrecoverableMessageHandlingException('No bookmark found.');

        $actorAccount = $bookmark->account;
        $owner = $actorAccount->owner
            ?? throw new UnrecoverableMessageHandlingException('Actor is not linked to an user.');

        /** @var array<Follower> $followers */
        $followers = $this->followerRepository->findByOwner($owner)
            ->getQuery()->getResult()
        ;

        $createActivity = $this->createActivityBundler->bundleFromBookmark($bookmark, $followers);
        $payload = $this->serializer->serialize($createActivity, 'json');

        $urls = [];
        foreach ($followers as $follower) {
            if ($url = $follower->account->sharedInboxUrl) {
                $urls[] = $url;
            } else {
                $this->logger->warning('Could not find sharedInboxUrl for Account.', [
                    'account' => $actorAccount->uri,
                ]);
            }
        }

        foreach (array_unique($urls) as $url) {
            $this->messageBus->dispatch(new SendMessage(
                payload: $payload,
                url: $url,
                accountUri: $actorAccount->uri
            ));
        }
    }
}
