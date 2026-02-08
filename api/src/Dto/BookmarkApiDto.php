<?php

namespace App\Dto;

use App\Entity\FileObject;
use App\Entity\UserTag;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'BookmarkCreate',
    description: 'Bookmark creation data',
    type: 'object',
    required: ['title', 'url'],
    properties: [
        new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public', default: false),
        new OA\Property(
            property: 'tags',
            type: 'array',
            description: 'Array of tag IRIs (must be valid IRIs pointing to existing tags owned by the user)',
            items: new OA\Items(type: 'string', format: 'iri', example: 'https://hivecache.test/users/me/tags/web-development')
        ),
        new OA\Property(property: 'mainImage', type: 'string', format: 'iri', nullable: true, description: 'IRI of main image FileObject'),
        new OA\Property(property: 'archive', type: 'string', format: 'iri', nullable: true, description: 'IRI of archive FileObject'),
    ]
)]
#[OA\Schema(
    schema: 'BookmarkUpdate',
    description: 'Bookmark update data',
    type: 'object',
    properties: [
        new OA\Property(property: 'title', type: 'string', description: 'New bookmark title'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public'),
        new OA\Property(
            property: 'tags',
            type: 'array',
            description: 'Array of tag IRIs. If omitted, existing tags are preserved.',
            items: new OA\Items(type: 'string', format: 'iri')
        ),
        new OA\Property(property: 'mainImage', type: 'string', format: 'iri', nullable: true, description: 'IRI of main image FileObject'),
        new OA\Property(property: 'archive', type: 'string', format: 'iri', nullable: true, description: 'IRI of archive FileObject'),
    ]
)]
class BookmarkApiDto
{
    #[Groups(['bookmark:create', 'bookmark:update'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    public ?string $title = null;

    /**
     * Assert\Url is too restrictive.
     */
    #[Groups(['bookmark:create'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    #[Assert\Regex(pattern: '`^[[:alnum:]-]+://.*\..*`', groups: ['bookmark:create'])]
    public ?string $url = null;

    #[Groups(['bookmark:create', 'bookmark:update'])]
    public ?bool $isPublic = null;

    /** @var array<int, UserTag> */
    #[Groups(['bookmark:create', 'bookmark:update'])]
    public array $tags = [];

    #[Groups(['bookmark:create', 'bookmark:update'])]
    public ?FileObject $mainImage = null;

    #[Groups(['bookmark:create', 'bookmark:update'])]
    public ?FileObject $archive = null;
}
