<?php

namespace App\Serializer;

use App\Config\RouteContext;
use App\Entity\Bookmark;
use App\Entity\UserTag;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class BookmarkNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: IriNormalizer::class)]
        private NormalizerInterface $normalizer,
        private RouteContext $routeContext,
    ) {
    }

    /**
     * @param Bookmark $data
     */
    public function normalize($data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$this->routeContext->getType()->isMe()) {
            // Remove non-public tags
            /* @phpstan-ignore-next-line */
            $data->userTags = new ArrayCollection(array_values($data->userTags->filter(fn (UserTag $tag) => $tag->isPublic)->toArray()));
        }

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Bookmark;
    }

    /**
     * @see https://symfony.com/doc/current/serializer/custom_normalizer.html#improving-performance-of-normalizers-denormalizers
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Bookmark::class => true,
        ];
    }
}
