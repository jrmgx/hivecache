<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Helper\RequestHelper;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '/profile/{username}/bookmarks', name: RouteType::ProfileBookmarks->value)]
final class ProfileBookmarkController extends BookmarkController
{
    #[OA\Get(
        path: '/profile/{username}/bookmarks',
        tags: ['Profile'],
        operationId: 'listPublicBookmarks',
        summary: 'List public bookmarks of a user',
        description: 'Returns a paginated collection of public bookmarks owned by the specified user. Supports filtering by tags (only public tags) and cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
        parameters: [
            new OA\PathParameter(
                name: 'username',
                description: 'Username',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'tags',
                description: 'Comma-separated list of tag slugs to filter by (only public tags)',
                schema: new OA\Schema(type: 'string', example: 'tag-one,tag-two')
            ),
            new OA\QueryParameter(
                name: 'q',
                description: 'Search query string',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\QueryParameter(
                name: 'after',
                description: 'Cursor for pagination - bookmark ID to fetch results after',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of public bookmarks',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/BookmarkShowPublic')
                        ),
                        new OA\Property(property: 'prevPage', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page if available'),
                        new OA\Property(property: 'total', type: 'integer', nullable: true),
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
        #[MapQueryParameter(name: 'tags')] ?string $tagQueryString = null,
        #[MapQueryParameter(name: 'q')] ?string $searchQueryString = null,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        return $this->collectionCommon(
            $account,
            $tagQueryString,
            $searchQueryString,
            $afterQueryString,
            ['bookmark:show:public', 'tag:show:public'],
            RouteType::ProfileBookmarks,
            ['username' => $account->username],
            onlyPublic: true
        );
    }

    #[OA\Get(
        path: '/profile/{username}/bookmarks/{id}',
        tags: ['Profile'],
        operationId: 'getPublicBookmark',
        summary: 'Get public bookmark by ID',
        description: 'Returns a specific public bookmark owned by the specified user.',
        parameters: [
            new OA\PathParameter(
                name: 'username',
                description: 'Username',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\PathParameter(
                name: 'id',
                description: 'Bookmark ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark details. Returns JSON by default (Accept: application/json) or HTML redirect (Accept: text/html).',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(ref: '#/components/schemas/BookmarkShowPublic')
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://bookmarkhive.net/profile/username/bookmarks/id'),
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
                        schema: new OA\Schema(type: 'string', format: 'uri', example: 'https://bookmarkhive.net/profile/username/bookmarks/id')
                    ),
                ]
            ),
            new OA\Response(
                response: 404,
                description: 'Bookmark not found, not public, or user not found'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        Request $request,
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
        string $id,
    ): Response {
        if (RequestHelper::accepts($request, 'application/json')) {
            $bookmark = $this->bookmarkRepository->findOneByAccountAndId($account, $id, onlyPublic: true)
                ->getQuery()->getOneOrNullResult()
                ?? throw new NotFoundHttpException()
            ;

            return $this->jsonResponseBuilder->single($bookmark, ['bookmark:show:public', 'tag:show:public']);
        }

        $iri = $this->generateUrl(RouteType::ProfileBookmarks->value . RouteAction::Get->value, [
            'id' => $id,
            'username' => $account->username,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($this->preferredClient . "?iri={$iri}");
    }
}
