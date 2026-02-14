<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Dto\AcceptFollowActivity;
use App\ActivityPub\Message\ReceiveAcceptMessage;
use App\Api\Enum\FollowStatus;
use App\Repository\FollowingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class ReceiveAcceptMessageHandler
{
    public function __construct(
        private SerializerInterface $serializer,
        private FollowingRepository $followingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ReceiveAcceptMessage $message): void
    {
        $acceptActivity = $this->serializer->deserialize($message->payload, AcceptFollowActivity::class, 'json');
        // For now, we only handle Accept Follow type
        if ('Follow' !== $acceptActivity->object->type) {
            throw new UnrecoverableMessageHandlingException('Not an Accept Follow type.');
        }

        $followingId = parse_url($acceptActivity->object->id, \PHP_URL_FRAGMENT);
        $following = $this->followingRepository->find($followingId)
            ?? throw new UnrecoverableMessageHandlingException('Following entity not found.');
        $following->status = FollowStatus::Confirmed;

        $this->entityManager->flush();
    }
}
