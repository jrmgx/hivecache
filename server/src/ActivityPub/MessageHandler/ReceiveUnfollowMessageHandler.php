<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Dto\UndoFollowActivity;
use App\ActivityPub\Message\ReceiveUnfollowMessage;
use App\Repository\FollowerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class ReceiveUnfollowMessageHandler
{
    public function __construct(
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager,
        private AccountFetch $accountFetch,
        private FollowerRepository $followerRepository,
    ) {
    }

    public function __invoke(ReceiveUnfollowMessage $message): void
    {
        $undoActivity = $this->serializer->deserialize($message->payload, UndoFollowActivity::class, 'json');

        if ('Follow' !== $undoActivity->object->type) {
            throw new UnrecoverableMessageHandlingException('Invalid Undo: object must be a Follow activity.');
        }

        $account = $this->accountFetch->fetchFromUri($undoActivity->actor);
        $user = $this->accountFetch->fetchFromUri($undoActivity->object->object)->owner
            ?? throw new UnrecoverableMessageHandlingException('No user matching this object.');

        if ($follower = $this->followerRepository->findOneByOwnerAndAccount($user, $account)) {
            $this->entityManager->remove($follower);
            $this->entityManager->flush();
        }
    }
}
