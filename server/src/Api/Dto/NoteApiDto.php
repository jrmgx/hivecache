<?php

namespace App\Api\Dto;

use App\Entity\Bookmark;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'NoteCreate',
    description: 'Note creation data',
    type: 'object',
    required: ['content', 'bookmark'],
    properties: [
        new OA\Property(property: 'content', type: 'string', description: 'Note content'),
        new OA\Property(property: 'bookmark', type: 'string', format: 'iri', description: 'IRI of the bookmark this note is associated with'),
    ]
)]
#[OA\Schema(
    schema: 'NoteUpdate',
    description: 'Note update data',
    type: 'object',
    properties: [
        new OA\Property(property: 'content', type: 'string', description: 'Note content'),
    ]
)]
class NoteApiDto
{
    #[Groups(['note:create', 'note:update'])]
    #[Assert\NotBlank(groups: ['note:create'])]
    public ?string $content = null;

    #[Groups(['note:create'])]
    #[Assert\NotBlank(groups: ['note:create'])]
    public ?Bookmark $bookmark = null;
}
