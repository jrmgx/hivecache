<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Dto\UserTagApiDto;
use App\Entity\User;
use App\Entity\UserTag;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/tags', name: RouteType::MeTags->value)]
final class MeTagController extends TagController
{
    #[OA\Get(
        path: '/users/me/tags',
        tags: ['Tags'],
        operationId: 'listOwnTags',
        summary: 'List own tags',
        description: 'Returns a collection of all tags owned by the authenticated user, including both public and private tags.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tags',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/TagShowPrivate')
                        ),
                        new OA\Property(property: 'total', type: 'integer'),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'tag_list',
                            value: [
                                'collection' => [
                                    [
                                        ...UserTag::EXAMPLE_TAG,
                                        'meta' => ['color' => 'blue'],
                                        'isPublic' => true,
                                    ],
                                ],
                                'total' => 1,
                            ],
                            summary: 'List of user tags'
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
    ): JsonResponse {
        return $this->collectionCommon($user, ['tag:show:private'], onlyPublic: false);
    }

    //    #[Route(path: '', name: RouteAction::A->value, methods: ['GET'])]
    //    public function a(
    //        #[CurrentUser] User $user,
    //    ): JsonResponse {
    //        $tags = $this->instanceTagService->findByOwner($user, onlyPublic: $onlyPublic)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //
    //        return $this->jsonResponseBuilder->collection(
    //            $tags, $groups, ['total' => \count($tags)]
    //        );
    //    }

    #[OA\Get(
        path: '/users/me/tags/{slug}',
        tags: ['Tags'],
        operationId: 'getOwnTag',
        summary: 'Get own tag by slug',
        description: 'Returns a specific tag owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'slug',
                description: 'Tag slug',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tag details',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/TagShowPrivate'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{slug}', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        #[CurrentUser] User $user,
        string $slug,
    ): JsonResponse {
        $userTag = $this->userTagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: false)
            ->getQuery()
            ->getOneOrNullResult() ?? throw new NotFoundHttpException()
        ;

        return $this->jsonResponseBuilder->single($userTag, ['tag:show:private']);
    }

    #[OA\Post(
        path: '/users/me/tags',
        tags: ['Tags'],
        operationId: 'createTag',
        summary: 'Create a new tag',
        description: 'Creates a new tag or returns an existing tag if a tag with the same name already exists. Maximum 1000 tags per user. Slug is automatically generated from the name and cannot be forced (slug field in request body is ignored).',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Tag data',
            content: new OA\JsonContent(ref: '#/components/schemas/TagCreate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tag created or existing tag returned',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/TagShowPrivate',
                    examples: [
                        new OA\Examples(
                            example: 'created_tag',
                            value: [
                                ...UserTag::EXAMPLE_TAG,
                                'meta' => [],
                                'isPublic' => false,
                            ],
                            summary: 'Newly created tag'
                        ),
                        new OA\Examples(
                            example: 'existing_tag',
                            value: [
                                ...UserTag::EXAMPLE_TAG,
                                'meta' => ['color' => 'blue'],
                                'isPublic' => true,
                            ],
                            summary: 'Existing tag returned'
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
                description: 'Validation error - maximum 1000 tags reached or invalid data',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'limit_reached',
                            value: ['error' => ['code' => 422, 'message' => 'You have reached the 1000 tags limit.']],
                            summary: 'Tag limit reached'
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
            serializationContext: ['groups' => ['tag:create']],
            validationGroups: ['Default', 'tag:create'],
        )]
        UserTagApiDto $tagPayload,
    ): JsonResponse {
        if ($this->userTagRepository->countByOwner($user) >= 1000) {
            throw new UnprocessableEntityHttpException('You have reached the 1000 tags limit.');
        }

        $userTag = new UserTag();
        $userTag->name = $tagPayload->name;
        $userTag->isPublic = $tagPayload->isPublic ?? false;
        $userTag->meta = $tagPayload->meta;
        $userTag->owner = $user;

        /** @noinspection PhpTypedPropertyMightBeUninitializedInspection */
        $existing = $this->userTagRepository->findOneByOwnerAndSlug($user, $userTag->slug, onlyPublic: false)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        if ($existing) {
            return $this->jsonResponseBuilder->single($existing, ['tag:show:private']);
        }

        $this->instanceTagService->findOrCreate($userTag->name);

        try {
            $this->entityManager->persist($userTag);
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($userTag, ['tag:show:private']);
    }

    #[OA\Patch(
        path: '/users/me/tags/{slug}',
        tags: ['Tags'],
        operationId: 'updateTag',
        summary: 'Update a tag',
        description: 'Updates an existing tag. Meta data is merged, not replaced. Changing the name will update the slug.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'slug',
                description: 'Tag slug',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Tag update data',
            content: new OA\JsonContent(ref: '#/components/schemas/TagUpdate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tag updated successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/TagShowPrivate'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found or not owned by user'
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - tag with new name already exists'
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
    #[Route(path: '/{slug}', name: RouteAction::Patch->value, methods: ['PATCH'])]
    public function patch(
        #[CurrentUser] User $user,
        string $slug,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['tag:update']],
            validationGroups: ['Default', 'tag:update'],
        )]
        UserTagApiDto $tagPayload,
    ): JsonResponse {
        $userTag = $this->userTagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: false)
            ->getQuery()
            ->getOneOrNullResult() ?? throw new NotFoundHttpException()
        ;

        if (isset($tagPayload->name) && $userTag->name !== $tagPayload->name) {
            $existingTag = $this->userTagRepository->findOneByOwnerAndSlug($user, $tagPayload->slug, onlyPublic: false)
                ->getQuery()
                ->getOneOrNullResult()
            ;

            if ($existingTag && $existingTag->id !== $userTag->id) {
                throw new ConflictHttpException();
            }

            $userTag->name = $tagPayload->name;
            $this->instanceTagService->findOrCreate($userTag->name);
        }

        if (isset($tagPayload->isPublic)) {
            $userTag->isPublic = $tagPayload->isPublic;
        }

        // Meta is merge only
        $userTag->meta = array_merge($userTag->meta, $tagPayload->meta);

        try {
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($userTag, ['tag:show:private']);
    }

    #[OA\Delete(
        path: '/users/me/tags/{slug}',
        tags: ['Tags'],
        operationId: 'deleteTag',
        summary: 'Delete a tag',
        description: 'Permanently deletes a tag owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'slug',
                description: 'Tag slug',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Tag deleted successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Tag not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{slug}', name: RouteAction::Delete->value, methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
        string $slug,
    ): JsonResponse {
        $userTag = $this->userTagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: false)
            ->getQuery()
            ->getOneOrNullResult() ?? throw new NotFoundHttpException()
        ;

        $this->entityManager->remove($userTag);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
