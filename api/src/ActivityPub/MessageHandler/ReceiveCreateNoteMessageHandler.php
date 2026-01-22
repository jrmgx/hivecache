<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\Bundler\NoteObjectBundler;
use App\ActivityPub\Dto\CreateNoteActivity;
use App\ActivityPub\Message\ReceiveCreateNoteMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
class ReceiveCreateNoteMessageHandler
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly NoteObjectBundler $noteObjectBundler,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ReceiveCreateNoteMessage $message): void
    {
        $createNoteActivity = $this->serializer->deserialize($message->payload, CreateNoteActivity::class, 'json');
        // For now we only handle Create Note type
        if ('Note' !== $createNoteActivity->object->type) {
            throw new UnrecoverableMessageHandlingException('Not an Create Note type.');
        }

        $bookmark = $this->noteObjectBundler->unbundleToBookmark($createNoteActivity->object);

        $this->entityManager->persist($bookmark);
        $this->entityManager->flush();
    }
}
