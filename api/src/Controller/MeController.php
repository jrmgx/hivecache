<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\User;
use App\Response\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/users/me', name: RouteType::Me->value)]
final class MeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[OA\Get(
        path: '/users/me',
        tags: ['User'],
        operationId: 'getCurrentUser',
        summary: 'Get current user profile',
        description: 'Returns the authenticated user\'s profile information including username, isPublic status, and meta data.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user profile',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserOwner',
                    examples: [
                        new OA\Examples(
                            example: 'user_profile',
                            value: [
                                'username' => 'johndoe',
                                'isPublic' => true,
                                'meta' => ['theme' => 'dark', 'language' => 'en'],
                                '@iri' => 'https://bookmarkhive.test/users/me',
                            ],
                            summary: 'Current user profile'
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
    #[Route(path: '', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        #[CurrentUser] User $user,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($user, ['user:show:private']);
    }

    #[OA\Patch(
        path: '/users/me',
        tags: ['User'],
        operationId: 'updateCurrentUser',
        summary: 'Update current user profile',
        description: 'Updates the authenticated user\'s profile. Username and password changes will invalidate existing JWT tokens. Meta data is merged, not replaced.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'User update data',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 32, description: 'New username'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, description: 'New password'),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public'),
                    new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs (merged with existing)', additionalProperties: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile updated successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserOwner'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
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
    #[Route(path: '', name: RouteAction::Patch->value, methods: ['PATCH'])]
    public function patch(
        #[CurrentUser] User $user,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['user:update']],
            validationGroups: ['Default'],
        )]
        User $userPayload,
    ): JsonResponse {
        // Manual merge
        // Meta is merge only
        $user->meta = array_merge($user->meta, $userPayload->meta);

        if (isset($userPayload->username) && $user->username !== $userPayload->username) {
            $user->username = $userPayload->username;
            // Changing username will invalidate JWT de-facto (as it is part of the payload)
            // but we make it explicit in code
            $user->rotateSecurity();
        }

        if ($userPayload->getPlainPassword()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $userPayload->getPlainPassword())
            );
            $userPayload->setPlainPassword(null);
            // By changing the password we want to invalidate all previous JWT
            $user->rotateSecurity();
        }

        $violations = $this->validator->validate($user, groups: ['user:update']);
        if ($violations->count() > 0) {
            throw new UnprocessableEntityHttpException();
        }

        try {
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($user, ['user:show:private']);
    }

    #[OA\Delete(
        path: '/users/me',
        tags: ['User'],
        operationId: 'deleteCurrentUser',
        summary: 'Delete current user account',
        description: 'Permanently deletes the authenticated user\'s account and all associated data. After deletion, accessing GET /users/me will return 404.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 204,
                description: 'User account deleted successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Delete->value, methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
    ): JsonResponse {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
