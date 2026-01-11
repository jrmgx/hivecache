<?php

declare(strict_types=1);

namespace App\Controller\ActivityPub;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Response\ActivityPubResponseBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '', name: RouteType::ActivityPub->value)]
class WebFingerController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        #[Autowire('%instanceHost%')]
        private readonly string $instanceHost,
    ) {
    }

    #[Route(path: '/.well-known/webfinger', name: RouteAction::WellKnown->value, methods: ['GET'])]
    public function index(
        #[MapQueryParameter] string $resource,
    ): JsonResponse {
        if (false === preg_match('`^acct:@?([A-Za-z0-9]+)@`', $resource, $match)) {
            throw new \InvalidArgumentException('Invalid resource format. Expected: acct:username@host');
        }

        $username = $match[1] ?? throw new \InvalidArgumentException('Username not found in resource');

        $profileUrl = $this->generateUrl(RouteType::Profile->value . RouteAction::Get->value, ['username' => $username], UrlGeneratorInterface::ABSOLUTE_URL);
        $data = [
            'subject' => "acct:{$username}@{$this->instanceHost}",
            'aliases' => [
                $profileUrl,
            ],
            'links' => [
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => $profileUrl,
                ],
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json', // TODO handle this accept header
                    'href' => $profileUrl,
                ],
            ],
        ];

        // TODO use that
        $this->activityPubResponseBuilder->single(null, []);

        return $this->json($data, headers: [
            'content-type' => 'application/jrd+json; charset=utf-8',
        ]);
    }
}
