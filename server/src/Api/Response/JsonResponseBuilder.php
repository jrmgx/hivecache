<?php

namespace App\Api\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class JsonResponseBuilder
{
    public function __construct(
        private DenormalizerInterface&NormalizerInterface $serializer,
    ) {
    }

    /**
     * @param array<mixed> $objects
     * @param list<string> $groups
     * @param array<mixed> $decoration
     */
    public function collection(array $objects, array $groups, array $decoration = []): JsonResponse
    {
        $contextBuilder = new ObjectNormalizerContextBuilder()
            ->withGroups($groups)
            ->withSkipNullValues(true)
        ;

        $data = $this->serializer->normalize($objects, 'array', $contextBuilder->toArray());

        return new JsonResponse(['collection' => $data, ...$decoration])
            ->setEncodingOptions(\JSON_UNESCAPED_SLASHES)
        ;
    }

    /**
     * @param list<string> $groups
     */
    public function single(mixed $object, array $groups): JsonResponse
    {
        $contextBuilder = new ObjectNormalizerContextBuilder()
            ->withGroups($groups)
            ->withSkipNullValues(true)
        ;

        $data = $this->serializer->normalize($object, 'json', $contextBuilder->toArray());

        return new JsonResponse($data)
            ->setEncodingOptions(\JSON_UNESCAPED_SLASHES)
        ;
    }
}
