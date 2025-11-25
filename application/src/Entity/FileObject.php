<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Processor\FileObjectMeProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity]
#[ApiResource(
    normalizationContext: ['groups' => ['file_object:read']],
    outputFormats: ['jsonld' => ['application/ld+json']],
    operations: [
        new Post(
            description: 'Create a new file to be associated with something',
            processor: FileObjectMeProcessor::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new Model\Operation(
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ])
                )
            )
        ),
    ]
)]
class FileObject
{
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['file_object:read'])]
    #[ApiProperty(types: ['https://schema.org/contentUrl'], writable: false)]
    public ?string $contentUrl = null;

    #[Vich\UploadableField(mapping: 'file_object', fileNameProperty: 'filePath')]
    public ?File $file = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(nullable: true)]
    public ?string $filePath = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
