<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Bookmark;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Bookmark>
 */
final class BookmarkVoter extends Voter
{
    public const string ACCOUNT = 'BOOKMARK_ACCOUNT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCOUNT === $attribute && $subject instanceof Bookmark;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Bookmark $bookmark */
        $bookmark = $subject;

        if (self::ACCOUNT === $attribute) {
            $user = $token->getUser();

            if (!$user instanceof User) {
                return false;
            }

            return $bookmark->account->owner === $user;
        }

        return false;
    }
}
