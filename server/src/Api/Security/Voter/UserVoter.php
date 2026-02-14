<?php

declare(strict_types=1);

namespace App\Api\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const string PUBLIC = 'USER_PUBLIC';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::PUBLIC === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $user */
        $user = $subject;

        if (self::PUBLIC === $attribute) {
            return $user->isPublic;
        }

        return false;
    }
}
