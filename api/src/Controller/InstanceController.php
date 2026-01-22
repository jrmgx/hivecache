<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Entity\Bookmark;
use App\Helper\RequestHelper;
use App\Repository\UserTagRepository;
use App\Response\JsonResponseBuilder;
use App\Service\InstanceTagService;
use App\Service\UrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

// TODO add tests
#[Route(path: '/instance', name: RouteType::Instance->value)]
final class InstanceController extends BookmarkController
{
    #[OA\Get(
        path: '/instance/this',
        tags: ['Instance'],
        operationId: 'listThisInstanceBookmarks',
        summary: 'List bookmarks from this instance',
        description: 'Returns a paginated collection of public bookmarks from this instance. Supports cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
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
                description: 'List of public bookmarks from this instance',
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
                            example: 'instance_bookmarks',
                            value: [
                                'collection' => [
                                    Bookmark::EXAMPLE_PUBLIC_BOOKMARK,
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/instance/this?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of bookmarks from this instance'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '/this', name: RouteAction::This->value, methods: ['GET'])]
    public function this(
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        $qb = $this->bookmarkRepository->findByThisInstance($this->instanceHost);
        return $this->responseFromQueryBuilder(
            $qb, $afterQueryString,
            ['bookmark:show:public', 'tag:show:public'],
            RouteType::Instance, RouteAction::This,
        );
    }

    #[OA\Get(
        path: '/instance/other',
        tags: ['Instance'],
        operationId: 'listOtherInstanceBookmarks',
        summary: 'List bookmarks from other instances',
        description: 'Returns a paginated collection of public bookmarks from other instances (federated bookmarks). Supports cursor-based pagination. Default page size is 24 items, ordered by creation date (newest first).',
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
                description: 'List of public bookmarks from other instances',
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
                            example: 'federated_bookmarks',
                            value: [
                                'collection' => [
                                    Bookmark::EXAMPLE_PUBLIC_BOOKMARK,
                                ],
                                'prevPage' => null,
                                'nextPage' => 'https://hivecache.test/instance/other?after=01234567-89ab-cdef-0123-456789abcdef',
                                'total' => null,
                            ],
                            summary: 'Paginated list of bookmarks from other instances'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '/other', name: RouteAction::Other->value, methods: ['GET'])]
    public function other(
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): Response {
        $qb = $this->bookmarkRepository->findByOtherInstance($this->instanceHost);
        return $this->responseFromQueryBuilder(
            $qb, $afterQueryString,
            ['bookmark:show:public', 'tag:show:public'],
            RouteType::Instance, RouteAction::Other,
        );
    }
}
