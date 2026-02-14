<?php

declare(strict_types=1);

namespace App\ActivityPub\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Response\ActivityPubResponseBuilder;
use App\Entity\Account;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '', name: RouteType::ActivityPub->value)]
class WebFingerController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
    ) {
    }

    #[Route(path: '/.well-known/webfinger', name: RouteAction::WellKnown->value, methods: ['GET'])]
    public function index(
        #[MapQueryParameter] string $resource,
    ): JsonResponse {
        if (!preg_match('`^acct:' . Account::USERNAME_REGEX . '@`', $resource, $match)) {
            throw new BadRequestHttpException('Invalid resource format. Expected: acct:username@host');
        }

        $username = $match[1];

        return $this->activityPubResponseBuilder->webfinger($username);
    }
}
