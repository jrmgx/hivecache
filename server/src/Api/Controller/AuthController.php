<?php

namespace App\Api\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class AuthController extends AbstractController
{
    /**
     * Authentication endpoint handled by Symfony security.
     * Documented here for OpenAPI generation.
     */
    #[OA\Post(
        path: '/auth',
        tags: ['Authentication'],
        operationId: 'authenticate',
        summary: 'Authenticate user',
        description: 'Authenticates a user with username and password, returns a JWT token for subsequent API requests.',
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Authentication credentials',
            content: new OA\JsonContent(
                type: 'object',
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', description: 'Username'),
                    new OA\Property(property: 'password', type: 'string', description: 'Password'),
                ],
                example: ['username' => 'janedoe', 'password' => 'password']
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentication successful',
                content: new OA\JsonContent(
                    type: 'object',
                    required: ['token'],
                    properties: [
                        new OA\Property(property: 'token', type: 'string', description: 'JWT bearer token'),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'success',
                            value: ['token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'],
                            summary: 'Authentication token'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Authentication failed - invalid credentials'
            ),
        ]
    )]
    public function auth(): never
    {
        throw new \RuntimeException();
    }
}
