<?php

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Entity\User;
use App\Repository\BookmarkIndexActionRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/bookmarks/search', name: RouteType::MeBookmarksIndex->value)]
final class MeBookmarkIndexController extends BookmarkController
{
    #[OA\Get(
        path: '/users/me/bookmarks/search/index',
        tags: ['Bookmarks', 'Search'],
        operationId: 'listOwnBookmarksIndex',
        summary: 'List own bookmarks for client side indexing',
        description: 'Returns a paginated collection of bookmarks owned by the authenticated user. Default page size is 100 items, ordered by creation date (newest first).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\QueryParameter(
                name: 'after',
                description: 'Cursor for pagination - bookmark ID to fetch results after',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of bookmarks',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/BookmarkShowPrivate')
                        ),
                        new OA\Property(property: 'prevPage', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page if available'),
                        new OA\Property(property: 'total', type: 'integer', nullable: true),
                    ],
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
        ]
    )]
    #[Route(path: '/index', name: RouteAction::Collection->value, methods: ['GET'])]
    public function collection(
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        return $this->collectionCommon(
            $user->account,
            null,
            $afterQueryString,
            ['bookmark:show:private', 'tag:show:private'],
            RouteType::MeBookmarksIndex,
            RouteAction::Collection,
            onlyPublic: false,
            resultPerPage: 100,
        );
    }

    #[OA\Get(
        path: '/users/me/bookmarks/search/diff',
        tags: ['Bookmarks', 'Search'],
        operationId: 'getBookmarkIndexDiff',
        summary: 'Get bookmark index changes',
        description: 'Returns a collection of bookmark index actions (changes) for the authenticated user. Used for syncing client-side index with server changes. Results are ordered by ID (ascending).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\QueryParameter(
                name: 'before',
                description: 'Cursor for pagination - index action ID to fetch results after',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of bookmark index actions',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/BookmarkIndexAction')
                        ),
                    ],
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
        ]
    )]
    #[Route(path: '/diff', name: RouteAction::Diff->value, methods: ['GET'])]
    public function diff(
        BookmarkIndexActionRepository $bookmarkIndexActionRepository,
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'before')] ?string $beforeQueryString = null,
    ): JsonResponse {
        $bookmarkIndexActions = $bookmarkIndexActionRepository->findByOwner($user, $beforeQueryString)
            ->getQuery()->getResult()
        ;

        return $this->jsonResponseBuilder->collection($bookmarkIndexActions, ['bookmark_index:show:private']);
    }
}
