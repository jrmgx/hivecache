<?php

namespace App\Controller;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Message\SendFollowMessage;
use App\ActivityPub\Message\SendUnfollowMessage;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Entity\Following;
use App\Entity\User;
use App\Helper\PaginationHelper;
use App\Repository\FollowingRepository;
use App\Response\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/following', name: RouteType::MeFollowing->value)]
final class MeFollowingController extends AbstractController
{
    public function __construct(
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly FollowingRepository $followingRepository,
        private readonly AccountFetch $accountFetch,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[OA\Get(
        path: '/users/me/following',
        tags: ['Following'],
        operationId: 'listOwnFollowing',
        summary: 'List accounts being followed',
        description: 'Returns a paginated collection of accounts that the authenticated user is following. Default page size is 100 items, ordered by creation date (newest first).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\QueryParameter(
                name: 'after',
                description: 'Cursor for pagination - account ID to fetch results after',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of followed accounts',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/FollowingShowPublic')
                        ),
                        new OA\Property(property: 'prevPage', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page if available'),
                        new OA\Property(property: 'total', type: 'integer', nullable: true),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'following_list',
                            value: [
                                'collection' => [
                                    [
                                        'id' => '01234567-89ab-cdef-0123-456789abcdef',
                                        'account' => Account::EXAMPLE_ACCOUNT,
                                        'createdAt' => '2024-01-01T12:00:00+00:00',
                                    ],
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/users/me/following?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of followed accounts'
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
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        $qb = $this->followingRepository->findByOwner($user);
        $qb = PaginationHelper::applyPagination($qb, $afterQueryString, 100);
        $followings = $qb->getQuery()->getResult();
        $count = 0; // TODO;

        return $this->jsonResponseBuilder->collection($followings, ['following:show:public', 'account:show:public'], [
            'prevPage' => null,
            'nextPage' => 'TODO',
            'total' => $count,
        ]);
    }

    #[OA\Post(
        path: '/users/me/following/{usernameWithInstance}',
        tags: ['Following'],
        operationId: 'followAccount',
        summary: 'Follow an account',
        description: 'Creates a follow relationship with the specified account by username.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'usernameWithInstance',
                description: 'Username of the account to follow (with instance)',
                schema: new OA\Schema(type: 'string', example: 'janedoe@hivecache.test')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account followed successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/FollowingShowPublic',
                    examples: [
                        new OA\Examples(
                            example: 'followed_account',
                            value: [
                                'id' => '01234567-89ab-cdef-0123-456789abcdef',
                                'account' => Account::EXAMPLE_ACCOUNT,
                                'createdAt' => '2024-01-01T12:00:00+00:00',
                            ],
                            summary: 'Successfully followed account'
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
                description: 'Account not found'
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - follow request already exist'
            ),
        ]
    )]
    // TODO usernameWithInstance could be asserted with Account::ACCOUNT_REGEX
    #[Route(path: '/{usernameWithInstance}', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        string $usernameWithInstance,
    ): JsonResponse {
        $account = $this->accountFetch->fetchFromUsernameInstance($usernameWithInstance);

        if ($this->followingRepository->findOneByOwnerAndAccount($user, $account)) {
            throw new ConflictHttpException('Follow request already exist.');
        }

        $following = new Following();
        $following->owner = $user;
        $following->account = $account;

        $this->entityManager->persist($following);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SendFollowMessage($following->id));

        return $this->jsonResponseBuilder->single($following, ['following:show:public', 'account:show:public']);
    }

    #[OA\Delete(
        path: '/users/me/following/{usernameWithInstance}',
        tags: ['Following'],
        operationId: 'unfollowAccount',
        summary: 'Unfollow an account',
        description: 'Removes the follow relationship with the specified account by username (with instance).',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'usernameWithInstance',
                description: 'Username of the account to unfollow',
                schema: new OA\Schema(type: 'string', example: 'janedoe@hivecache.test')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Account unfollowed successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Account not found'
            ),
        ]
    )]
    #[Route(path: '/{usernameWithInstance}', name: RouteAction::Delete->value, methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
        string $usernameWithInstance,
    ): JsonResponse {
        $account = $this->accountFetch->fetchFromUsernameInstance($usernameWithInstance);
        $following = $this->followingRepository->findOneByOwnerAndAccount($user, $account);
        if ($following) {
            $this->entityManager->remove($following);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new SendUnfollowMessage($user->id, $account->id));
        }

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
