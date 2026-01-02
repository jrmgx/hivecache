<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
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
                            items: new OA\Items(ref: '#/components/schemas/TagProfile')
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
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
    ): JsonResponse {
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
                description: 'Public tag details',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/TagProfile'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found, not public, or user not found'
            ),
        ]
    )]
    #[Route(path: '/{slug}', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
        string $slug,
    ): JsonResponse {
        $tag = $this->tagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: true)
            ->getQuery()->getOneOrNullResult()
            ?? throw new NotFoundHttpException()
        ;

        return $this->jsonResponseBuilder->single($tag, ['tag:show:public']);
    }
}
