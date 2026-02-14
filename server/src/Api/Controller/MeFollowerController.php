<?php

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Helper\PaginationHelper;
use App\Api\Response\JsonResponseBuilder;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\FollowerRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/followers', name: RouteType::MeFollowers->value)]
final class MeFollowerController extends AbstractController
{
    public function __construct(
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly FollowerRepository $followerRepository,
    ) {
    }

    #[OA\Get(
        path: '/users/me/followers',
        tags: ['Followers'],
        operationId: 'listOwnFollowers',
        summary: 'List own followers',
        description: 'Returns a paginated collection of accounts that follow the authenticated user. Default page size is 100 items, ordered by creation date (newest first).',
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
                description: 'List of follower accounts',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'collection',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/FollowerShowPublic')
                        ),
                        new OA\Property(property: 'prevPage', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page if available'),
                        new OA\Property(property: 'total', type: 'integer', nullable: true),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'follower_list',
                            value: [
                                'collection' => [
                                    [
                                        'id' => '01234567-89ab-cdef-0123-456789abcdef',
                                        'account' => Account::EXAMPLE_ACCOUNT,
                                        'createdAt' => '2024-01-01T12:00:00+00:00',
                                    ],
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/users/me/followers?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of followers'
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
        $qb = $this->followerRepository->findByOwner($user);
        $qb = PaginationHelper::applyPagination($qb, $afterQueryString, 100);
        $followers = $qb->getQuery()->getResult();
        $count = 0; // TODO;

        return $this->jsonResponseBuilder->collection($followers, ['follower:show:public', 'account:show:public'], [
            'prevPage' => null,
            'nextPage' => 'TODO',
            'total' => $count,
        ]);
    }
}
