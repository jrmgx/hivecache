<?php

namespace App\Api\Serializer;

use App\Entity\FileObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class FileObjectNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: IriNormalizer::class)]
        private NormalizerInterface $normalizer,
        private string $storageDefaultPublicPath,
        private string $baseUri,
    ) {
    }

    /**
     * @param FileObject $data
     */
    public function normalize($data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        if ($data->owner) {
            /* @phpstan-ignore-next-line */
            $normalizedData['contentUrl'] =
                // TODO use flysystem instead
                $this->baseUri . $this->storageDefaultPublicPath . '/' . $data->filePath;
        } else {
            /* @phpstan-ignore-next-line */
            $normalizedData['contentUrl'] = $data->filePath;
        }

        return $normalizedData;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FileObject;
    }

    /**
     * @see https://symfony.com/doc/current/serializer/custom_normalizer.html#improving-performance-of-normalizers-denormalizers
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            FileObject::class => true,
        ];
    }
}
