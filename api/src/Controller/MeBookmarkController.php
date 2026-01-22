<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace App\Controller;

use App\ActivityPub\Message\SendCreateNoteMessage;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Dto\BookmarkApiDto;
use App\Entity\Account;
use App\Entity\Bookmark;
use App\Entity\User;
use App\Entity\UserTag;
use App\Enum\BookmarkIndexActionType;
use App\Security\Voter\BookmarkVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
                    examples: [
                        new OA\Examples(
                            example: 'bookmark_list',
                            value: [
                                'collection' => [
                                    [...Bookmark::EXAMPLE_PUBLIC_BOOKMARK, 'isPublic' => true],
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/users/me/bookmarks?after=01234567-89ab-cdef-0123-456789abcdef',
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
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        return $this->collectionCommon(
            $user->account,
            $tagQueryString,
            $afterQueryString,
            ['bookmark:show:private', 'tag:show:private'],
            RouteType::MeBookmarks,
            RouteAction::Collection,
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
            content: new OA\JsonContent(ref: '#/components/schemas/BookmarkCreate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark created successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BookmarkShowPrivate',
                    examples: [
                        new OA\Examples(
                            example: 'created_bookmark',
                            value: [
                                'id' => Bookmark::EXAMPLE_BOOKMARK_ID,
                                'createdAt' => '2024-01-01T12:00:00+00:00',
                                'title' => 'Example Bookmark',
                                'url' => 'https://example.com',
                                'domain' => 'example.com',
                                'account' => Account::EXAMPLE_ACCOUNT,
                                'isPublic' => false,
                                'tags' => [
                                    UserTag::EXAMPLE_TAG,
                                ],
                                'instance' => 'hivecache.test',
                                '@iri' => Bookmark::EXAMPLE_BOOKMARK_IRI,
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
        BookmarkApiDto $bookmarkPayload,
    ): JsonResponse {
        // Find previous version and outdate it
        /** @var ?Bookmark $existingBookmark */
        $existingBookmark = $this->bookmarkRepository->findLastOneByAccountAndUrl(
            $user->account,
            $bookmarkPayload->url ?? throw new BadRequestHttpException()
        )->getQuery()->getOneOrNullResult();

        $bookmark = new Bookmark();
        $bookmark->title = $bookmarkPayload->title ?? throw new BadRequestHttpException();
        $bookmark->url = $bookmarkPayload->url;
        $bookmark->isPublic = $bookmarkPayload->isPublic ?? false;
        $bookmark->account = $user->account;
        $bookmark->instance = $this->instanceHost;
        $bookmark->userTags = new ArrayCollection($bookmarkPayload->tags);
        $bookmark->mainImage = $bookmarkPayload->mainImage;
        $bookmark->archive = $bookmarkPayload->archive;

        if ($existingBookmark) {
            $existingBookmark->outdated = true;

            $indexAction = $this->indexActionUpdater->update($existingBookmark, BookmarkIndexActionType::Outdated);
            $this->entityManager->persist($indexAction);

            $bookmark->mergeUserTags($existingBookmark->userTags);

            if ($existingBookmark->isPublic) {
                $bookmark->isPublic = true;
            }
        }

        $this->instanceTagService->synchronize($bookmark);

        try {
            $this->entityManager->persist($bookmark);

            $indexAction = $this->indexActionUpdater->update($bookmark, BookmarkIndexActionType::Created);
            $this->entityManager->persist($indexAction);

            $this->entityManager->flush();

            if ($bookmark->isPublic && !$existingBookmark) {
                $this->messageBus->dispatch(new SendCreateNoteMessage($bookmark->id));
            }
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
                    ref: '#/components/schemas/BookmarkShowPrivate'
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
    #[IsGranted(attribute: BookmarkVoter::ACCOUNT, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
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
                            items: new OA\Items(ref: '#/components/schemas/BookmarkShowPrivate')
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
    #[IsGranted(attribute: BookmarkVoter::ACCOUNT, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function history(
        #[CurrentUser] User $user,
        Bookmark $bookmark,
    ): JsonResponse {
        // If you call history on an outdated version it will also work by design
        $bookmarks = $this->bookmarkRepository->findOutdatedByAccountAndUrl($user->account, $bookmark->url)
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
            content: new OA\JsonContent(ref: '#/components/schemas/BookmarkUpdate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bookmark updated successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BookmarkShowPrivate'
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
    #[IsGranted(attribute: BookmarkVoter::ACCOUNT, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function patch(
        Bookmark $bookmark,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['bookmark:update']],
            validationGroups: ['Default', 'bookmark:update'],
        )]
        BookmarkApiDto $bookmarkPayload,
    ): JsonResponse {
        // Manual merge
        if (isset($bookmarkPayload->title)) {
            $bookmark->title = $bookmarkPayload->title;
        }
        if (isset($bookmarkPayload->isPublic)) {
            $bookmark->isPublic = $bookmarkPayload->isPublic;
        }
        if (\count($bookmarkPayload->tags) > 0) {
            /* @phpstan-ignore-next-line $bookmarkPayload->tags is an array of UserTag as denormalized by IriDenormalizer */
            $bookmark->userTags = new ArrayCollection($bookmarkPayload->tags);
        }
        //        if (isset($bookmarkPayload->mainImage)) {
        //            $bookmark->mainImage = $bookmarkPayload->mainImage;
        //        }
        //        if (isset($bookmarkPayload->archive)) {
        //            $bookmark->archive = $bookmarkPayload->archive;
        //        }

        $this->instanceTagService->synchronize($bookmark);

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
    #[IsGranted(attribute: BookmarkVoter::ACCOUNT, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(
        #[CurrentUser] User $user,
        Bookmark $bookmark,
    ): JsonResponse {
        $indexAction = $this->indexActionUpdater->update($bookmark, BookmarkIndexActionType::Deleted);
        $this->entityManager->persist($indexAction);

        $this->bookmarkRepository->deleteByAccountAndUrl($user->account, $bookmark->url);

        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
