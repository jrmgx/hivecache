<?php

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Helper\RequestHelper;
use App\Entity\Account;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/profile/{username}/tags', name: RouteType::ProfileTags->value)]
final class ProfileTagController extends TagController
{
    #[OA\Get(
        path: '/profile/{username}/tags',
        tags: ['Profile'],
        operationId: 'listPublicTags',
        summary: 'List public tags of a user',
        description: 'Returns a collection of public tags owned by the specified user.',
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
                description: 'List of public tags',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/TagShowPublic')
                        ),
                        new OA\Property(property: 'total', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found'
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Collection->value, methods: ['GET'])]
    public function collection(
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
    ): JsonResponse {
        $user = $account->owner ?? throw new NotFoundHttpException();

        return $this->collectionCommon($user, ['tag:show:public'], onlyPublic: true);
    }

    #[OA\Get(
        path: '/profile/{username}/tags/{slug}',
        tags: ['Profile'],
        operationId: 'getPublicTag',
        summary: 'Get public tag by slug',
        description: 'Returns a specific public tag owned by the specified user.',
        parameters: [
            new OA\PathParameter(
                name: 'username',
                description: 'Username',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'slug',
                description: 'Tag slug',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public tag details. Returns JSON by default (Accept: application/json) or HTML redirect (Accept: text/html).',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(ref: '#/components/schemas/TagShowPublic')
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://hivecache.net/profile/username/tags/tag-slug'),
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://hivecache.net/profile/username/tags/tag-slug')
                    ),
                ]
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found, not public, or user not found'
            ),
        ]
    )]
    #[Route(path: '/{slug}', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        Request $request,
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
        string $slug,
    ): Response {
        $user = $account->owner ?? throw new NotFoundHttpException();

        if (RequestHelper::accepts($request, ['text/html'])) {
            $iri = $this->urlGenerator->generate(
                RouteType::ProfileTags,
                RouteAction::Get,
                ['slug' => $slug, 'username' => $user->username]
            );

            return new RedirectResponse($this->preferredClient . "?iri={$iri}");
        }

        $tag = $this->userTagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: true)
            ->getQuery()->getOneOrNullResult()
            ?? throw new NotFoundHttpException()
        ;

        return $this->jsonResponseBuilder->single($tag, ['tag:show:public']);
    }
}
