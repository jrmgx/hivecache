<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Bundler\NoteObjectBundler;
use App\ActivityPub\Dto\CreateNoteActivity;
use App\ActivityPub\Message\ReceiveCreateNoteMessage;
use App\Entity\UserTimelineEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Serializer\SerializerInterface;

#[AsMessageHandler]
final readonly class ReceiveCreateNoteMessageHandler
{
    public function __construct(
        private SerializerInterface $serializer,
        private NoteObjectBundler $noteObjectBundler,
        private EntityManagerInterface $entityManager,
        private AccountFetch $accountFetch,
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
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

        // Associate to given user's timelines
        foreach ($createNoteActivity->cc as $uri) {
            if (str_starts_with($uri, 'https://' . $this->instanceHost . '/')) {
                $account = $this->accountFetch->fetchFromUriOrNull($uri);
                if (!$account?->owner) {
                    continue;
                }

                $timelineEntry = new UserTimelineEntry();
                $timelineEntry->owner = $account->owner;
                $timelineEntry->bookmark = $bookmark;
                $this->entityManager->persist($timelineEntry);
                $this->entityManager->flush();
            }
        }
    }
}
