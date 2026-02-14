<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Bundler\UndoFollowActivityBundler;
use App\ActivityPub\Message\SendMessage;
use App\ActivityPub\Message\SendUnfollowMessage;
use App\Repository\FollowingRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class SendUnfollowMessageHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private FollowingRepository $followingRepository,
        private UndoFollowActivityBundler $undoFollowActivityBundler,
    ) {
    }

    public function __invoke(SendUnfollowMessage $message): void
    {
        $following = $this->followingRepository->find($message->followingId)
            ?? throw new UnrecoverableMessageHandlingException('No following entity matching.');

        $actorAccount = $following->owner->account;
        $objectAccount = $following->account;

        $url = $objectAccount->inboxUrl
            ?? throw new UnrecoverableMessageHandlingException('No inbox url for actor.');

        $undo = $this->undoFollowActivityBundler->bundleFromFollowing($following);

        $this->messageBus->dispatch(new SendMessage(
            payload: $this->serializer->serialize($undo, 'json'),
            url: $url,
            accountUri: $actorAccount->uri
        ));
    }
}
