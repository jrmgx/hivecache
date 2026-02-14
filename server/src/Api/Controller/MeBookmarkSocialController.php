<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\InstanceTag;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/bookmarks/social', name: RouteType::MeBookmarks->value)]
final class MeBookmarkSocialController extends BookmarkController
{
    #[OA\Get(
        path: '/users/me/bookmarks/social/timeline',
        tags: ['Bookmarks'],
        operationId: 'listSocialTimeline',
        summary: 'Get social timeline',
        description: 'Returns a paginated collection of public bookmarks from the social timeline. The timeline includes bookmarks from users you follow and public bookmarks from your instance. Supports cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
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
                description: 'List of public bookmarks from timeline',
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
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'timeline_bookmarks',
                            value: [
                                'collection' => [
                                    Bookmark::EXAMPLE_PUBLIC_BOOKMARK,
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/users/me/bookmarks/timeline?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of timeline bookmarks'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
        ]
    )]
    #[Route(path: '/timeline', name: RouteAction::SocialTimeline->value, methods: ['GET'])]
    public function timeline(
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        $qb = $this->bookmarkRepository->findTimelineByOwner($user);

        return $this->responseFromQueryBuilder(
            $qb,
            $afterQueryString,
            ['bookmark:show:public', 'tag:show:public'],
            RouteType::MeBookmarks,
            RouteAction::SocialTimeline,
        );
    }

    #[OA\Get(
        path: '/users/me/bookmarks/social/tag/{slug}',
        tags: ['Bookmarks'],
        operationId: 'listSocialTagBookmarks',
        summary: 'Get bookmarks by instance tag',
        description: 'Returns a paginated collection of public bookmarks filtered by an instance tag. Instance tags are tags that are used across multiple users on the instance. Supports cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'slug',
                description: 'Instance tag slug',
                schema: new OA\Schema(type: 'string', example: 'example-tag')
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
                description: 'List of public bookmarks with the specified tag',
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
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'tagged_bookmarks',
                            value: [
                                'collection' => [
                                    Bookmark::EXAMPLE_PUBLIC_BOOKMARK,
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/users/me/bookmarks/tag/example-tag?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of bookmarks with tag'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Instance tag not found'
            ),
        ]
    )]
    #[Route(path: '/tag/{slug}', name: RouteAction::SocialTag->value, methods: ['GET'])]
    public function tag(
        #[MapEntity(mapping: ['slug' => 'slug'])] InstanceTag $instanceTag,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        $qb = $this->bookmarkRepository->findTimelineByInstanceTag($instanceTag);

        return $this->responseFromQueryBuilder(
            $qb,
            $afterQueryString,
            ['bookmark:show:public', 'tag:show:public'],
            RouteType::MeBookmarks,
            RouteAction::SocialTag,
            ['slug' => $instanceTag->slug]
        );
    }
}
