<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

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

    #[Groups(['file_object:read', 'bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Column()]
    public int $size;

    #[Groups(['file_object:read', 'bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Column()]
    public string $mime;

    #[ORM\Column()]
    public string $filePath;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
