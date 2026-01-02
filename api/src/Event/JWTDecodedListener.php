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
final readonly class JWTDecodedListener
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $rotation = $payload['rotation'] ?? throw new BadRequestHttpException();
        $username = $payload['username'] ?? throw new BadRequestHttpException();
        $user = $this->userRepository->loadUserByIdentifier($username) ?? throw new NotFoundHttpException();

        if ($rotation !== $user->securityInvalidation) {
            $event->markAsInvalid();
        }
    }
}
