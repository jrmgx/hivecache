<?php

namespace App\Api\Serializer;

use App\Entity\FileObject;
use App\Entity\User;
use App\Entity\UserTag;
use App\Repository\FileObjectRepository;
use App\Repository\UserTagRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

readonly class IriDenormalizer implements DenormalizerInterface
{
    private const string PATH_TAGS = '`/users/me/tags/([a-z0-9-]+)`';
    private const string PATH_FILE_OBJECTS = '`/users/me/files/([a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12})`';

    public function __construct(
        private Security $security,
        private UserTagRepository $userTagRepository,
        private FileObjectRepository $fileObjectRepository,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @var User $user */
        $user = $this->security->getUser() ?? throw new \LogicException('No user logged.');

        if (UserTag::class === $type) {
            $path = (string) parse_url($data, \PHP_URL_PATH);
            $matches = [];
            if (!preg_match(self::PATH_TAGS, $path, $matches)) {
                throw new UnprocessableEntityHttpException('This Tag does not exist.');
            }
            $slug = $matches[1];

            return $this->userTagRepository->findOneByOwnerAndSlug($user, $slug, onlyPublic: false)
                ->getQuery()->getOneOrNullResult()
                ?? throw new UnprocessableEntityHttpException('This Tag does not exist.')
            ;
        }

        if (FileObject::class === $type) {
            $path = (string) parse_url($data, \PHP_URL_PATH);
            $matches = [];
            if (!preg_match(self::PATH_FILE_OBJECTS, $path, $matches)) {
                throw new UnprocessableEntityHttpException('This FileObject does not exist.');
            }
            $id = $matches[1];

            return $this->fileObjectRepository->findOneByOwnerAndId($user, $id)
                ->getQuery()->getOneOrNullResult()
                ?? throw new UnprocessableEntityHttpException('This FileObject does not exist.')
            ;
        }

        return $data;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        if (\is_string($data) && (UserTag::class === $type || FileObject::class === $type)) {
            $path = (string) parse_url($data, \PHP_URL_PATH);

            return preg_match(self::PATH_TAGS, $path) || preg_match(self::PATH_FILE_OBJECTS, $path);
        }

        return false;
    }

    /**
     * @see https://symfony.com/doc/current/serializer/custom_normalizer.html#improving-performance-of-normalizers-denormalizers
     *
     * @return array<mixed>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            UserTag::class => true,
            FileObject::class => true,
        ];
    }
}
