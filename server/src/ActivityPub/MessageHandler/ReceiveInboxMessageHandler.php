<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Message\ReceiveAcceptMessage;
use App\ActivityPub\Message\ReceiveFollowMessage;
use App\ActivityPub\Message\ReceiveInboxMessage;
use App\ActivityPub\Message\ReceiveUnfollowMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ReceiveInboxMessageHandler extends AbstractSignedMessageHandler
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        string $instanceHost,
        #[Autowire('%env(APP_ENV)%')]
        string $appEnv,
        AccountFetch $accountFetch,
        private MessageBusInterface $messageBus,
    ) {
        parent::__construct($instanceHost, $appEnv, $accountFetch);
    }

    public function __invoke(ReceiveInboxMessage $message): void
    {
        $this->validateMessageOrThrow($message);

        // Route message
        $data = json_decode($message->body, true);
        if (!isset($data['type'])) {
            throw new UnrecoverableMessageHandlingException('Missing type in message.');
        }

        $type = $data['type'];
        if ('Accept' === $type) {
            $this->messageBus->dispatch(new ReceiveAcceptMessage($message->body));

            return;
        }

        if ('Follow' === $type) {
            $this->messageBus->dispatch(new ReceiveFollowMessage($message->body));

            return;
        }

        if ('Undo' === $type && isset($data['object']['type']) && 'Follow' === $data['object']['type']) {
            $this->messageBus->dispatch(new ReceiveUnfollowMessage($message->body));
        }
    }
}
