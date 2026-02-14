<?php

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Response\JsonResponseBuilder;
use App\Api\UserFactory;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/register', name: RouteType::Register->value)]
final class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly UserFactory $userFactory,
        #[Autowire('%env(ACCOUNT_LIMIT)%')]
        private readonly int $accountLimit,
    ) {
    }

    #[OA\Post(
        path: '/register',
        tags: ['Register'],
        operationId: 'createUser',
        summary: 'Register a new user',
        description: 'Creates a new user with username and password. Returns the created user object and associated account.',
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
                ],
                example: ['username' => 'janedoe', 'password' => 'password']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User account created successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserShowPrivate',
                    examples: [
                        new OA\Examples(
                            example: 'success',
                            value: [
                                'username' => 'janedoe',
                                'isPublic' => false,
                                'meta' => [],
                                'account' => Account::EXAMPLE_ACCOUNT,
                                '@iri' => User::EXAMPLE_USER_IRI,
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
        User $userInput,
    ): JsonResponse {
        if ($this->userRepository->countAll() >= $this->accountLimit) {
            throw new AccessDeniedHttpException('This instance does not allow new accounts.');
        }

        if ($this->userRepository->usernameExist(mb_strtolower($userInput->username))) {
            throw new ConflictHttpException('Username already exists.');
        }

        [$user] = $this->userFactory->new(
            $userInput->username,
            $userInput->getPlainPassword() ?? throw new \LogicException(),
            $userInput->isPublic,
            $userInput->meta
        );

        $this->entityManager->flush();

        return $this->jsonResponseBuilder->single($user, ['user:show:private']);
    }
}
