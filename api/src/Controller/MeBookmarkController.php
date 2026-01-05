<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\User;
use App\Enum\BookmarkIndexActionType;
use App\Security\Voter\BookmarkVoter;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/users/me/bookmarks', name: RouteType::MeBookmarks->value)]
final class MeBookmarkController extends BookmarkController
{
    #[OA\Get(
        path: '/users/me/bookmarks',
        tags: ['Bookmarks'],
        operationId: 'listOwnBookmarks',
        summary: 'List own bookmarks',
        description: 'Returns a paginated collection of bookmarks owned by the authenticated user. Supports filtering by tags and cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\QueryParameter(
                name: 'tags',
                description: 'Comma-separated list of tag slugs to filter by',
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
                description: 'List of bookmarks',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/BookmarkOwner')
                        ),
                        new OA\Property(property: 'prevPage', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page if available'),
                        new OA\Property(property: 'total', type: 'integer', nullable: true),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'bookmark_list',
                            value: [
                                'collection' => [
                                    [
                                        'id' => '01234567-89ab-cdef-0123-456789abcdef',
                                        'createdAt' => '2024-01-01T12:00:00+00:00',
                                        'title' => 'Example Bookmark',
                                        'url' => 'https://example.com',
                                        'domain' => 'example.com',
                                        'owner' => ['username' => 'johndoe', '@iri' => 'https://bookmarkhive.test/users/me'],
                                        'isPublic' => true,
                                        'tags' => [
                                            ['name' => 'Web Development', 'slug' => 'web-development', '@iri' => 'https://bookmarkhive.test/users/me/tags/web-development'],
                                        ],
                                        '@iri' => 'https://bookmarkhive.test/users/me/bookmarks/01234567-89ab-cdef-0123-456789abcdef',
                                    ],
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://bookmarkhive.test/users/me/bookmarks?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of bookmarks'
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
    #[Route(path: '', name: RouteAction::Collection->value, methods: ['GET'])]
    public function collection(
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'tags')] ?string $tagQueryString = null,
        #[MapQueryParameter(name: 'q')] ?string $searchQueryString = null,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        return $this->collectionCommon(
            $user,
            $tagQueryString,
            $searchQueryString,
            $afterQueryString,
            ['bookmark:show:private', 'tag:show:private'],
            RouteType::MeBookmarks,
            onlyPublic: false
        );
    }

    #[OA\Post(
        path: '/users/me/bookmarks',
        tags: ['Bookmarks'],
        operationId: 'createBookmark',
        summary: 'Create a new bookmark',
        description: 'Creates a new bookmark. If a bookmark with the same URL already exists, the previous one will be marked as outdated.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Bookmark data',
            content: new OA\JsonContent(
                type: 'object',
                required: ['title', 'url'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
                    new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public', default: false),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        description: 'Array of tag IRIs (must be valid IRIs pointing to existing tags owned by the user)',
                        items: new OA\Items(type: 'string', format: 'iri', example: 'https://bookmarkhive.test/users/me/tags/web-development')
                    ),
                    new OA\Property(property: 'mainImage', type: 'string', format: 'iri', nullable: true, description: 'IRI of main image FileObject'),
                    new OA\Property(property: 'archive', type: 'string', format: 'iri', nullable: true, description: 'IRI of archive FileObject'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark created successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BookmarkOwner',
                    examples: [
                        new OA\Examples(
                            example: 'created_bookmark',
                            value: [
                                'id' => '01234567-89ab-cdef-0123-456789abcdef',
                                'createdAt' => '2024-01-01T12:00:00+00:00',
                                'title' => 'Example Bookmark',
                                'url' => 'https://example.com',
                                'domain' => 'example.com',
                                'owner' => ['username' => 'johndoe', '@iri' => 'https://bookmarkhive.test/users/me'],
                                'isPublic' => false,
                                'tags' => [
                                    ['name' => 'Web Development', 'slug' => 'web-development', '@iri' => 'https://bookmarkhive.test/users/me/tags/web-development'],
                                ],
                                '@iri' => 'https://bookmarkhive.test/users/me/bookmarks/01234567-89ab-cdef-0123-456789abcdef',
                            ],
                            summary: 'Successfully created bookmark'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error - invalid data or invalid tag IRIs',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'invalid_tag_iri',
                            value: ['error' => ['code' => 422, 'message' => 'Invalid tag IRI provided']],
                            summary: 'Invalid tag reference'
                        ),
                        new OA\Examples(
                            example: 'tags_as_objects',
                            value: ['error' => ['code' => 422, 'message' => 'Tags must be IRIs, not objects']],
                            summary: 'Tags provided as JSON objects instead of IRIs'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['bookmark:create']],
            validationGroups: ['Default', 'bookmark:create'],
        )]
        Bookmark $bookmark,
    ): JsonResponse {
        // Find previous version and outdate it
        /** @var ?Bookmark $existingBookmark */
        $existingBookmark = $this->bookmarkRepository->findLastOneByOwnerAndUrl($user, $bookmark->url)
            ->getQuery()->getOneOrNullResult()
        ;
        if ($existingBookmark) {
            $existingBookmark->outdated = true;

            $indexAction = $this->indexActionUpdater->update($existingBookmark, BookmarkIndexActionType::Outdated);
            $this->entityManager->persist($indexAction);

            if ($existingBookmark->isPublic) {
                $bookmark->isPublic = true;
            }
        }

        $bookmark->owner = $user;

        try {
            $this->entityManager->persist($bookmark);

            $indexAction = $this->indexActionUpdater->update($bookmark, BookmarkIndexActionType::Created);
            $this->entityManager->persist($indexAction);

            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:show:private', 'tag:show:private']);
    }

    #[OA\Get(
        path: '/users/me/bookmarks/{id}',
        tags: ['Bookmarks'],
        operationId: 'getOwnBookmark',
        summary: 'Get own bookmark by ID',
        description: 'Returns a specific bookmark owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Bookmark ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark details',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BookmarkOwner'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Bookmark not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Get->value, methods: ['GET'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function get(
        Bookmark $bookmark,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:show:private', 'tag:show:private']);
    }

    #[OA\Get(
        path: '/users/me/bookmarks/{id}/history',
        tags: ['Bookmarks'],
        operationId: 'getBookmarkHistory',
        summary: 'Get bookmark history',
        description: 'Returns the history of outdated bookmarks with the same normalized URL as the given bookmark. The current bookmark is not included in the history. Results are ordered by creation date (newest first).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Bookmark ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of outdated bookmarks',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/BookmarkOwner')
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
                description: 'Bookmark not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{id}/history', name: RouteAction::History->value, methods: ['GET'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function history(
        #[CurrentUser] User $user,
        Bookmark $bookmark,
    ): JsonResponse {
        // If you call history on an outdated version it will also work by design
        $bookmarks = $this->bookmarkRepository->findOutdatedByOwnerAndUrl($user, $bookmark->url)
            ->getQuery()->getResult()
        ;

        return $this->jsonResponseBuilder->collection($bookmarks, ['bookmark:show:private', 'tag:show:private']);
    }

    #[OA\Patch(
        path: '/users/me/bookmarks/{id}',
        tags: ['Bookmarks'],
        operationId: 'updateBookmark',
        summary: 'Update a bookmark',
        description: 'Updates an existing bookmark. URL cannot be changed after creation (URL field in request body is ignored). Tags are preserved if not included in the request body.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Bookmark ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Bookmark update data',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: 'New bookmark title'),
                    new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Ignored - URL cannot be changed after creation', deprecated: true),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public'),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        description: 'Array of tag IRIs. If omitted, existing tags are preserved.',
                        items: new OA\Items(type: 'string', format: 'iri')
                    ),
                    new OA\Property(property: 'mainImage', type: 'string', format: 'iri', nullable: true, description: 'IRI of main image FileObject'),
                    new OA\Property(property: 'archive', type: 'string', format: 'iri', nullable: true, description: 'IRI of archive FileObject'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark updated successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BookmarkOwner'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Bookmark not found or not owned by user'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error - invalid data',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'invalid_data',
                            value: ['error' => ['code' => 422, 'message' => 'Unprocessable Content']],
                            summary: 'Validation error'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Patch->value, methods: ['PATCH'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function patch(
        Bookmark $bookmark,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['bookmark:update']],
            validationGroups: ['Default', 'bookmark:update'],
        )]
        Bookmark $bookmarkPayload,
    ): JsonResponse {
        // Manual merge
        if (isset($bookmarkPayload->title)) {
            $bookmark->title = $bookmarkPayload->title;
        }
        if (isset($bookmarkPayload->isPublic)) {
            $bookmark->isPublic = $bookmarkPayload->isPublic;
        }
        if (isset($bookmarkPayload->tags)) {
            $bookmark->tags = $bookmarkPayload->tags;
        }

        try {
            $indexAction = $this->indexActionUpdater->update($bookmark, BookmarkIndexActionType::Updated);
            $this->entityManager->persist($indexAction);

            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:show:private', 'tag:show:private']);
    }

    #[OA\Delete(
        path: '/users/me/bookmarks/{id}',
        tags: ['Bookmarks'],
        operationId: 'deleteBookmark',
        summary: 'Delete a bookmark',
        description: 'Permanently deletes a bookmark (and all its versions/history) owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Bookmark ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Bookmark deleted successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Bookmark not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Delete->value, methods: ['DELETE'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(
        #[CurrentUser] User $user,
        Bookmark $bookmark,
    ): JsonResponse {
        $indexAction = $this->indexActionUpdater->update($bookmark, BookmarkIndexActionType::Deleted);
        $this->entityManager->persist($indexAction);

        $this->bookmarkRepository->deleteByOwnerAndUrl($user, $bookmark->url);

        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
