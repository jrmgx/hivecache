<?php

declare(strict_types=1);

namespace App\ActivityPub\Controller;

use App\ActivityPub\Message\ReceiveInboxMessage;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Response\ActivityPubResponseBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/ap', name: RouteType::ActivityPub->value)]
class InboxController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/u/{username}/inbox', name: RouteAction::Inbox->value, methods: ['POST'])]
    public function inbox(Request $request, string $username): JsonResponse
    {
        $this->messageBus->dispatch(new ReceiveInboxMessage(
            $username,
            $request->getContent(),
            $request->getHost() . ($request->getPort() ? ':' . $request->getPort() : ''),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->headers->all(),
        ));

        return $this->activityPubResponseBuilder->ok();
    }
}
