<?php

namespace App\Event;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsEventListener(
    event: 'lexik_jwt_authentication.on_jwt_decoded',
    method: 'onJWTDecoded'
)]
class JWTDecodedListener
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $rotation = $payload['rotation'] ?? throw new BadRequestHttpException();
        $email = $payload['email'] ?? throw new BadRequestHttpException();
        $user = $this->userRepository->findOneByEmail($email) ?? throw new NotFoundHttpException();

        if ($rotation !== $user->securityInvalidation) {
            $event->markAsInvalid();
        }
    }
}
