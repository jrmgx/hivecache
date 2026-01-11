<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Helper\RequestHelper;
use App\Response\JsonResponseBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '/profile/{username}', name: RouteType::Profile->value)]
final class ProfileController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        private readonly string $preferredClient,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://bookmarkhive.net/profile/username'),
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://bookmarkhive.net/profile/username')
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
        if (RequestHelper::accepts($request, 'application/json')) {
            return $this->jsonResponseBuilder->single($account, ['account:show:public']);
        }

        $iri = $this->generateUrl(RouteType::Profile->value . RouteAction::Get->value, [
            'username' => $account->username,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($this->preferredClient . "?iri={$iri}");
    }
}
