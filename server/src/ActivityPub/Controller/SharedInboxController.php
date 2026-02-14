<?php

declare(strict_types=1);

namespace App\ActivityPub\Controller;

use App\ActivityPub\Message\ReceiveSharedInboxMessage;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Response\ActivityPubResponseBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/ap', name: RouteType::ActivityPub->value)]
class SharedInboxController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/inbox', name: RouteAction::SharedInbox->value, methods: ['GET', 'POST'])]
    public function inbox(
        Request $request,
    ): JsonResponse {
        $this->messageBus->dispatch(new ReceiveSharedInboxMessage(
            $request->getContent(),
            $request->getHost(),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->headers->all(),
        ));

        return $this->activityPubResponseBuilder->ok();
    }
}
