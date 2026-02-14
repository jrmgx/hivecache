<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Dto\FollowActivity;
use App\ActivityPub\Message\ReceiveFollowMessage;
use App\ActivityPub\Message\SendAcceptMessage;
use App\Entity\Follower;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class ReceiveFollowMessageHandler
{
    public function __construct(
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager,
        private AccountFetch $accountFetch,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ReceiveFollowMessage $message): void
    {
        $followActivity = $this->serializer->deserialize($message->payload, FollowActivity::class, 'json');

        $account = $this->accountFetch->fetchFromUri($followActivity->actor);
        $user = $this->accountFetch->fetchFromUri($followActivity->object)->owner
            ?? throw new UnrecoverableMessageHandlingException('No user matching this object.');

        $follower = new Follower();
        $follower->account = $account;
        $follower->owner = $user;

        $this->entityManager->persist($follower);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SendAcceptMessage(
            followerId: $follower->id,
            followPayload: $message->payload,
        ));
    }
}
