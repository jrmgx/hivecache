<?php

namespace App\Api\Serializer;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteContext;
use App\Api\Config\RouteType;
use App\Api\UrlGenerator;
use App\Entity\Bookmark;
use App\Entity\FileObject;
use App\Entity\User;
use App\Entity\UserTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class IriNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private UrlGenerator $urlGenerator,
        private RouteContext $routeContext,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($this->routeContext->getType()->isMe()) {
            $iri = match ($data::class) {
                User::class => $this->urlGenerator->generate(RouteType::Me, RouteAction::Get),
                Bookmark::class => $this->urlGenerator->generate(
                    RouteType::MeBookmarks,
                    RouteAction::Get,
                    ['id' => $data->id],
                ),
                UserTag::class => $this->urlGenerator->generate(
                    RouteType::MeTags,
                    RouteAction::Get,
                    ['slug' => $data->slug],
                ),
                FileObject::class => $this->urlGenerator->generate(
                    RouteType::MeFileObjects,
                    RouteAction::Get,
                    ['id' => $data->id],
                ),
                default => null,
            };
        } else {
            $iri = match ($data::class) {
                User::class => $this->urlGenerator->generate(
                    RouteType::Profile,
                    RouteAction::Get,
                    ['username' => $data->username],
                ),
                Bookmark::class => $this->urlGenerator->generate(
                    RouteType::ProfileBookmarks,
                    RouteAction::Get,
                    ['username' => $data->account->username, 'id' => $data->id],
                ),
                UserTag::class => $this->urlGenerator->generate(
                    RouteType::ProfileTags,
                    RouteAction::Get,
                    ['username' => $data->owner->username, 'slug' => $data->slug],
                ),
                default => null,
            };
        }

        if ($iri) {
            /* @phpstan-ignore-next-line */
            $normalizedData['@iri'] = $iri;
        }

        return $normalizedData;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return
            $data instanceof User
            || $data instanceof UserTag;
        // ||$data instanceof Bookmark
        // || $data instanceof FileObject
    }

    /**
     * @see https://symfony.com/doc/current/serializer/custom_normalizer.html#improving-performance-of-normalizers-denormalizers
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
            UserTag::class => true,
            // Bookmark::class => true,
            // FileObject::class => true,
        ];
    }
}
