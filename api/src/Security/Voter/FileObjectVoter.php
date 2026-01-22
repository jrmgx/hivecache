<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\FileObject;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, FileObject>
 */
final class FileObjectVoter extends Voter
{
    public const string OWNER = 'FILE_OBJECT_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute && $subject instanceof FileObject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var FileObject $fileObject */
        $fileObject = $subject;
        if (!$fileObject->owner) {
            return false;
        }

        if (self::OWNER === $attribute) {
            $user = $token->getUser();

            if (!$user instanceof User) {
                return false;
            }

            return $fileObject->owner === $user;
        }

        return false;
    }
}
