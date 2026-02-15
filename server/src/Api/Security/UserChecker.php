<?php

namespace App\Api\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->active) {
            throw new CustomUserMessageAccountStatusException('Account is not activated. Please contact an administrator.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
