<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Bundler\AcceptFollowActivityBundler;
use App\ActivityPub\Dto\FollowActivity;
use App\ActivityPub\Message\SendAcceptMessage;
use App\ActivityPub\Message\SendMessage;
use App\Repository\FollowerRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class SendAcceptMessageHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private SerializerInterface $serializer,
        private FollowerRepository $followerRepository,
        private AcceptFollowActivityBundler $acceptFollowActivityBundler,
    ) {
    }

    public function __invoke(SendAcceptMessage $message): void
    {
        $follower = $this->followerRepository->find($message->followerId)
            ?? throw new UnrecoverableMessageHandlingException('No follower entity matching.');

        $followActivity = $this->serializer->deserialize($message->followPayload, FollowActivity::class, 'json');

        $actorAccount = $follower->owner->account;
        $objectAccount = $follower->account;

        $url = $objectAccount->inboxUrl
            ?? throw new UnrecoverableMessageHandlingException('No inbox url for actor.');

        $accept = $this->acceptFollowActivityBundler->bundleFromFollowerAndFollow($follower, $followActivity);

        $this->messageBus->dispatch(new SendMessage(
            payload: $this->serializer->serialize($accept, 'json'),
            url: $url,
            accountUri: $actorAccount->uri
        ));
    }
}
