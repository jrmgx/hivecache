<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\OpenApi(
    info: new OA\Info(
        title: 'BookmarkHive API',
        version: '0.1',
        description: 'Decentralized social bookmarking service based on the Activity Pub protocol.'
    ),
    servers: [
        new OA\Server(url: 'https://bookmarkhive.test', description: 'API Dev Server'),
        new OA\Server(url: 'https://api2.bookmarkhive.test', description: 'API 2 Dev Server'),
    ],
    x: [
        'tagGroups' => [
            ['name' => 'Authentication & Account', 'tags' => ['Authentication', 'Account']],
            ['name' => 'User Management', 'tags' => ['User']],
            ['name' => 'Bookmarks', 'tags' => ['Bookmarks']],
            ['name' => 'Tags', 'tags' => ['Tags']],
            ['name' => 'Files', 'tags' => ['Files']],
            ['name' => 'Public Profiles', 'tags' => ['Profile']],
        ],
    ]
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT token obtained from POST /auth endpoint'
)]
#[OA\Schema(
    schema: 'CollectionResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'collection', type: 'array', description: 'Array of items', items: new OA\Items()),
        new OA\Property(property: 'prevPage', type: 'string', nullable: true, description: 'URL for previous page'),
        new OA\Property(property: 'nextPage', type: 'string', nullable: true, description: 'URL for next page'),
        new OA\Property(property: 'total', type: 'integer', nullable: true, description: 'Total number of items'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    type: 'object',
    required: ['error'],
    properties: [
        new OA\Property(
            property: 'error',
            type: 'object',
            description: 'Error object',
            properties: [
                new OA\Property(property: 'code', type: 'integer', description: 'HTTP status code'),
                new OA\Property(property: 'message', type: 'string', description: 'Error message'),
            ],
            required: ['code', 'message']
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthorizedResponse',
    type: 'object',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 401),
        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found'),
    ]
)]
class AppController extends AbstractController
{
    /** @return array<mixed> */
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    #[Template('base.html.twig')]
    public function index(): array
    {
        return [];
    }

    #[Route(path: '/docs', name: 'docs')]
    public function docs(): JsonResponse
    {
        // TODO when prod update the servers def and urls on the fly for the real instance
        $response = new JsonResponse(file_get_contents(__DIR__ . '/../../openapi.json'), json: true);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization');

        return $response;
    }
}
