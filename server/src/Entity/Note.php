<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    // Serialization groups: ['note:show:private']
    // Validation groups: ['Default']
    schema: 'NoteShowPrivate',
    description: 'Note object owner-only',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Note ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'content', type: 'string', description: 'Note content'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the note resource'),
    ]
)]
#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note
{
    public const string EXAMPLE_NOTE_ID = '01234567-89ab-cdef-0123-456789abcdef';
    public const string EXAMPLE_NOTE_IRI = 'https://hivecache.test/users/me/notes/' . self::EXAMPLE_NOTE_ID;

    #[Groups(['note:show:private'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['note:show:private'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['note:show:private'])]
    #[ORM\Column(type: Types::TEXT)]
    public string $content;

    #[Ignore]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Ignore]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Bookmark $bookmark;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
