<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Tag;
use App\Entity\User;
use App\Security\Voter\TagVoter;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
                                        ...Tag::EXAMPLE_TAG,
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
    #[IsGranted(attribute: TagVoter::OWNER, subject: 'tag', statusCode: Response::HTTP_NOT_FOUND)]
    public function get(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($tag, ['tag:show:private']);
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
            content: new OA\JsonContent(
                type: 'object',
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 32, description: 'Tag name (slug is auto-generated from name)'),
                    new OA\Property(property: 'slug', type: 'string', description: 'Ignored - slug is automatically generated from name', deprecated: true),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public', default: false),
                    new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs', additionalProperties: true),
                ]
            )
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
                                ...Tag::EXAMPLE_TAG,
                                'meta' => [],
                                'isPublic' => false,
                            ],
                            summary: 'Newly created tag'
                        ),
                        new OA\Examples(
                            example: 'existing_tag',
                            value: [
                                ...Tag::EXAMPLE_TAG,
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
        Tag $tag,
    ): JsonResponse {
        if ($this->tagRepository->countByOwner($user) >= 1000) {
            throw new UnprocessableEntityHttpException('You have reached the 1000 tags limit.');
        }

        $existing = $this->tagRepository->findOneByOwnerAndSlug($user, $tag->slug, onlyPublic: false)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        if (!$existing) {
            $existing = $tag;
            $tag->owner = $user;

            try {
                $this->entityManager->persist($tag);
                $this->entityManager->flush();
            } catch (ORMInvalidArgumentException|ORMException $e) {
                throw new UnprocessableEntityHttpException(previous: $e);
            }
        }

        return $this->jsonResponseBuilder->single($existing, ['tag:show:private']);
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
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 32, description: 'New tag name'),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public'),
                    new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs (merged with existing)', additionalProperties: true),
                ]
            )
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
    #[IsGranted(attribute: TagVoter::OWNER, subject: 'tag', statusCode: Response::HTTP_NOT_FOUND)]
    public function patch(
        #[CurrentUser] User $user,
        #[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['tag:update']],
            validationGroups: ['Default', 'tag:update'],
        )]
        Tag $tagPayload,
    ): JsonResponse {
        // Manual merge
        if (isset($tagPayload->name) && $tag->name !== $tagPayload->name) {
            $tag->name = $tagPayload->name;
            if ($this->tagRepository->findOneByOwnerAndSlug($user, $tag->slug, onlyPublic: false)
                ->getQuery()
                ->getOneOrNullResult()) {
                throw new ConflictHttpException();
            }
        }
        // Update isPublic if provided
        if (isset($tagPayload->isPublic)) {
            $tag->isPublic = $tagPayload->isPublic;
        }
        // Meta is merge only
        $tag->meta = array_merge($tag->meta, $tagPayload->meta);

        try {
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($tag, ['tag:show:private']);
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
    #[IsGranted(attribute: TagVoter::OWNER, subject: 'tag', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag,
    ): JsonResponse {
        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
