<?php

namespace App\Entity;

use App\Enum\BookmarkIndexActionType;
use App\Repository\BookmarkIndexActionRepository;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    schema: 'BookmarkIndexAction',
    description: 'Bookmark index action representing a change to a bookmark',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Index action ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(
            property: 'type',
            type: 'string',
            enum: ['created', 'updated', 'deleted', 'outdated'],
            description: 'Type of action performed on the bookmark'
        ),
        new OA\Property(property: 'bookmark', type: 'string', format: 'uuid', description: 'The bookmark id associated with this action'),
    ]
)]
#[ORM\Entity(repositoryClass: BookmarkIndexActionRepository::class)]
class BookmarkIndexAction
{
    #[Groups(['bookmark_index:show:private'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public string $id;

    #[Groups(['bookmark_index:show:private'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['bookmark_index:show:private'])]
    #[ORM\Column(enumType: BookmarkIndexActionType::class)]
    public BookmarkIndexActionType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    public Bookmark $bookmark {
        set {
            $this->bookmark = $value;
            $this->bookmarkId = $value->id;
        }
    }

    #[Groups(['bookmark_index:show:private'])]
    #[SerializedName('bookmark')]
    #[ORM\Column(type: 'uuid')]
    public string $bookmarkId;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
