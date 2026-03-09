<?php

namespace App\Api\MessageHandler;

use App\Api\InstanceTagService;
use App\Api\Message\SynchronizeBookmarksForUserTagMessage;
use App\Repository\UserTagRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class SynchronizeBookmarksForUserTagMessageHandler
{
    public function __construct(
        private UserTagRepository $userTagRepository,
        private InstanceTagService $instanceTagService,
    ) {
    }

    public function __invoke(SynchronizeBookmarksForUserTagMessage $message): void
    {
        $userTag = $this->userTagRepository->find($message->userTagId)
            ?? throw new UnrecoverableMessageHandlingException('User tag not found.');

        $this->instanceTagService->synchronizeBookmarksForUserTag($userTag);
    }
}
