<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\OpenApi(
    info: new OA\Info(
        title: 'HiveCache API',
        version: '0.1',
        description: 'Decentralized social bookmarking service based on the Activity Pub protocol.'
    ),
    servers: [
        new OA\Server(url: 'https://hivecache.test', description: 'API Dev Server'),
        new OA\Server(url: 'https://api2.hivecache.test', description: 'API 2 Dev Server'),
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
final class AppController extends AbstractController
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private readonly string $instanceHost,
        #[Autowire('%env(APP_ENV)%')]
        private readonly string $appEnv,
    ) {
    }

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
        $json = file_get_contents(__DIR__ . '/../../openapi.json');
        if (!$json) {
            throw new \RuntimeException();
        }

        if ('prod' === $this->appEnv) {
            $data = json_decode($json, true);
            $data['servers'] = [['url' => 'https://' . $this->instanceHost, 'description' => 'HiveCache API server']];
            $json = json_encode($data);
            if (!$json) {
                throw new \RuntimeException();
            }

            $json = str_replace('hivecache.test', $this->instanceHost, $json);
        }

        return new JsonResponse($json, json: true);
        //        $response->headers->set('Access-Control-Allow-Origin', '*');
        //        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        //        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization');
    }
}
