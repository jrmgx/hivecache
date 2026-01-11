<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Response\ActivityPubResponseBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/ap', name: RouteType::ActivityPub->value)]
class SharedInboxController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
    ) {
    }

    #[Route(path: '/inbox', name: RouteAction::SharedInbox->value, methods: ['GET'])]
    public function inbox(): JsonResponse
    {
        return $this->activityPubResponseBuilder->single(null, []);
    }
}
