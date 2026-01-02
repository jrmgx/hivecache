<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Response\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/account', name: RouteType::Account->value)]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(ACCOUNT_LIMIT)%')]
        private readonly int $accountLimit,
    ) {
    }

    #[OA\Post(
        path: '/account',
        tags: ['Account'],
        operationId: 'createAccount',
        summary: 'Register a new user account',
        description: 'Creates a new user account with username and password. Returns the created user object.',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'User registration data',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UserCreate',
                type: 'object',
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 32, description: 'Unique username'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, description: 'User password'),
                    new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public', default: false),
                    new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata as key-value pairs', additionalProperties: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User account created successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserOwner',
                    examples: [
                        new OA\Examples(
                            example: 'success',
                            value: [
                                'username' => 'johndoe',
                                'isPublic' => false,
                                'meta' => [],
                                '@iri' => 'https://bookmarkhive.test/users/me',
                            ],
                            summary: 'Successfully created user account'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error - username already exists or invalid data',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'duplicate_username',
                            value: ['error' => ['code' => 422, 'message' => 'Username already exists']],
                            summary: 'Username already taken'
                        ),
                        new OA\Examples(
                            example: 'invalid_data',
                            value: ['error' => ['code' => 422, 'message' => 'Username must be between 3 and 32 characters']],
                            summary: 'Validation error'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - instance does not allow new accounts'
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - username already exist with a different set of case',
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[MapRequestPayload(
            serializationContext: ['groups' => ['user:create']],
            validationGroups: ['Default', 'user:create'],
        )]
        User $user,
    ): JsonResponse {
        if ($this->userRepository->countAll() >= $this->accountLimit) {
            throw new AccessDeniedHttpException('This instance does not allow new accounts.');
        }

        if ($this->userRepository->usernameExist(mb_strtolower($user->username))) {
            throw new ConflictHttpException('Username already exists.');
        }

        /** @var string $plainPassword asserted by validator */
        $plainPassword = $user->getPlainPassword();
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );
        $user->setPlainPassword(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->jsonResponseBuilder->single($user, ['user:show:private']);
    }
}
