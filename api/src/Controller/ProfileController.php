<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\User;
use App\Response\JsonResponseBuilder;
use App\Security\Voter\UserVoter;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/profile/{username}', name: RouteType::Profile->value)]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly JsonResponseBuilder $jsonResponseBuilder,
    ) {
    }

    #[OA\Get(
        path: '/profile/{username}',
        tags: ['Profile'],
        operationId: 'getPublicProfile',
        summary: 'Get public user profile',
        description: 'Returns the public profile information of a user. Only works if the user has set their profile as public.',
        parameters: [
            new OA\PathParameter(
                name: 'username',
                description: 'Username',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public user profile',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserProfile',
                    examples: [
                        new OA\Examples(
                            example: 'public_profile',
                            value: [
                                'username' => 'johndoe',
                                '@iri' => 'https://bookmarkhive.test/profile/johndoe',
                            ],
                            summary: 'Public user profile'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found or profile is not public'
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Get->value, methods: ['GET'])]
    #[IsGranted(attribute: UserVoter::PUBLIC, subject: 'user', statusCode: Response::HTTP_NOT_FOUND)]
    public function get(
        #[MapEntity(mapping: ['username' => 'username'])] User $user,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($user, ['user:show:public']);
    }
}
