<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Message\ReceiveCreateNoteMessage;
use App\ActivityPub\Message\ReceiveSharedInboxMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ReceiveSharedInboxMessageHandler extends AbstractSignedMessageHandler
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

    public function __invoke(ReceiveSharedInboxMessage $message): void
    {
        $this->validateMessageOrThrow($message);

        // Route message
        $data = json_decode($message->body, true);
        if (!isset($data['type'])) {
            throw new UnrecoverableMessageHandlingException('Missing type in message.');
        }

        $type = $data['type'];
        if ('Create' === $type) {
            // For now we only handle Create activity about Note object
            $this->messageBus->dispatch(new ReceiveCreateNoteMessage($message->body));

            // return;
        }
    }
}
