<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\User;
use App\Response\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/api/users/me', name: RouteType::Me->value)]
final class MeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route(path: '', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(
        #[CurrentUser] User $user,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($user, ['user:owner']);
    }

    #[Route(path: '', name: RouteAction::Patch->value, methods: ['PATCH'])]
    public function patch(
        #[CurrentUser] User $user,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['user:owner']],
            validationGroups: ['Default'],
        )]
        User $userPayload,
    ): JsonResponse {
        // Manual merge
        $user->username = $userPayload->username ?? $user->username;
        $user->email = $userPayload->email ?? $user->email;
        $user->meta = array_merge($user->meta, $userPayload->meta);

        if ($userPayload->getPlainPassword()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $userPayload->getPlainPassword())
            );
            $userPayload->setPlainPassword(null);
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

        return $this->jsonResponseBuilder->single($user, ['user:owner']);
    }

    #[Route(path: '', name: RouteAction::Delete->value, methods: ['DELETE'])]
    public function delete(
        #[CurrentUser] User $user,
    ): JsonResponse {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
