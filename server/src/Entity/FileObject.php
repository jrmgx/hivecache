<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    schema: 'FileObject',
    description: 'File object representing an uploaded file',
    type: 'object',
    properties: [
        new OA\Property(property: 'contentUrl', type: 'string', format: 'uri', nullable: true, description: 'URL to access the file content'),
        new OA\Property(property: 'size', type: 'integer', description: 'File size in bytes'),
        new OA\Property(property: 'mime', type: 'string', description: 'MIME type of the file'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the file object resource'),
    ]
)]
#[ORM\Entity]
#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
class FileObject
{
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['file_object:read', 'bookmark:show:private', 'bookmark:show:public'])]
    public ?string $contentUrl = null;

    /** @var int Size in bytes */
    #[Groups(['file_object:read', 'bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Column]
    public int $size;

    #[Groups(['file_object:read', 'bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Column]
    public string $mime;

    #[ORM\Column]
    public string $filePath;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    public ?User $owner = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
