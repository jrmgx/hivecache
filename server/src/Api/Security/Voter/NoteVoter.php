<?php

declare(strict_types=1);

namespace App\Api\Security\Voter;

use App\Entity\Note;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Note>
 */
final class NoteVoter extends Voter
{
    public const string OWNER = 'NOTE_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute && $subject instanceof Note;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $note = $subject;

        if (self::OWNER === $attribute) {
            $user = $token->getUser();

            if (!$user instanceof User) {
                return false;
            }

            return $note->owner === $user;
        }

        return false;
    }
}
