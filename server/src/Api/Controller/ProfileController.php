<?php

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Helper\RequestHelper;
use App\Api\Response\ActivityPubResponseBuilder;
use App\Api\Response\JsonResponseBuilder;
use App\Api\UrlGenerator;
use App\Entity\Account;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profile/{username}', name: RouteType::Profile->value)]
final class ProfileController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        private readonly string $preferredClient,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        private readonly UrlGenerator $urlGenerator,
    ) {
    }

    #[OA\Get(
        path: '/profile/{username}',
        tags: ['Profile'],
        operationId: 'getPublicProfile',
        summary: 'Get public user profile',
        description: 'Returns the public profile information of a user. Only works if the user has set their profile as public.',
        parameters: [
            new OA\PathParameter(
                name: 'username',
                description: 'Username',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public user profile. Returns JSON by default (Accept: application/json) or HTML redirect (Accept: text/html).',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(ref: '#/components/schemas/AccountShowPublic'),
                        examples: [
                            new OA\Examples(
                                example: 'public_profile',
                                value: Account::EXAMPLE_ACCOUNT,
                                summary: 'Public user profile'
                            ),
                        ]
                    ),
                    new OA\MediaType(
                        mediaType: 'text/html',
                        schema: new OA\Schema(
                            type: 'string',
                            description: 'HTML redirect response'
                        )
                    ),
                ],
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'Redirect URL (when Accept: text/html)',
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://hivecache.net/profile/username'),
                        required: false
                    ),
                ]
            ),
            new OA\Response(
                response: 301,
                description: 'Redirect response (when Accept: text/html)',
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'Redirect URL',
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://hivecache.net/profile/username')
                    ),
                ]
            ),
            new OA\Response(
                response: 404,
                description: 'User not found or profile is not public'
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        Request $request,
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
    ): Response {
        // Activity Pub
        if (RequestHelper::accepts($request, ['application/activity+json', 'application/ld+json'])) {
            return $this->activityPubResponseBuilder->profile($account);
        }

        if (RequestHelper::accepts($request, ['text/html'])) {
            $iri = $this->urlGenerator->generate(
                RouteType::Profile,
                RouteAction::Get,
                ['username' => $account->username]
            );

            return new RedirectResponse($this->preferredClient . "?iri={$iri}");
        }

        return $this->jsonResponseBuilder->single($account, ['account:show:public']);
    }
}
