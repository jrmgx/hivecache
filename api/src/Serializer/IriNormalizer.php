<?php

namespace App\Serializer;

use App\Config\RouteAction;
use App\Config\RouteContext;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\FileObject;
use App\Entity\Tag;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class IriNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
        private UrlGeneratorInterface $router,
        private RouteContext $routeContext,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($this->routeContext->getType()->isMe()) {
            $iri = match ($data::class) {
                User::class => $this->router->generate(RouteType::Me->value . RouteAction::Get->value, [], UrlGeneratorInterface::ABSOLUTE_URL),
                Bookmark::class => $this->router->generate(RouteType::MeBookmarks->value . RouteAction::Get->value, [
                    'id' => $data->id,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                Tag::class => $this->router->generate(RouteType::MeTags->value . RouteAction::Get->value, [
                    'slug' => $data->slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                FileObject::class => $this->router->generate(RouteType::MeFileObjects->value . RouteAction::Get->value, [
                    'id' => $data->id,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                default => null,
            };
        } else {
            $iri = match ($data::class) {
                User::class => $this->router->generate(RouteType::Profile->value . RouteAction::Get->value, [
                    'username' => $data->username,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                Bookmark::class => $this->router->generate(RouteType::ProfileBookmarks->value . RouteAction::Get->value, [
                    'username' => $data->account->username,
                    'id' => $data->id,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                Tag::class => $this->router->generate(RouteType::ProfileTags->value . RouteAction::Get->value, [
                    'username' => $data->owner->username,
                    'slug' => $data->slug,
                ], UrlGeneratorInterface::ABSOLUTE_URL),
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
            || $data instanceof Tag;
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
            Tag::class => true,
            // Bookmark::class => true,
            // FileObject::class => true,
        ];
    }
}
