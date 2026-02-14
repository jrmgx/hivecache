<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Bundler\DeleteActivityBundler;
use App\ActivityPub\Message\SendDeleteTombstoneMessage;
use App\ActivityPub\Message\SendMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class SendDeleteTombstoneMessageHandler
{
    public function __construct(
        private DeleteActivityBundler $deleteActivityBundler,
        private SerializerInterface $serializer,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(SendDeleteTombstoneMessage $message): void
    {
        $deleteActivity = $this->deleteActivityBundler->bundle(
            $message->actorUri,
            $message->bookmarkUri,
            $message->bookmarkUrl,
        );
        $payload = $this->serializer->serialize($deleteActivity, 'json');

        foreach ($message->sharedInboxUrls as $url) {
            $this->messageBus->dispatch(new SendMessage(
                payload: $payload,
                url: $url,
                accountUri: $message->actorUri
            ));
        }
    }
}
