<?php

namespace App\Entity;

use App\Helper\UrlHelper;
use App\Repository\BookmarkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    // Serialization groups: ['bookmark:show:private']
    // Validation groups: ['Default']
    schema: 'BookmarkShowPrivate',
    description: 'Bookmark object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Bookmark ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
        new OA\Property(property: 'domain', type: 'string', description: 'Extracted domain from URL'),
        new OA\Property(property: 'account', type: 'object', description: 'Account that owns the bookmark', ref: '#/components/schemas/AccountShowPublic'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the bookmark is public'),
        new OA\Property(property: 'tags', type: 'array', description: 'Associated tags', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'mainImage', type: 'object', nullable: true, description: 'Main image file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'archive', type: 'object', nullable: true, description: 'Archive file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'instance', type: 'string', description: 'Instance host where the bookmark was created'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the bookmark resource'),
    ]
)]
#[OA\Schema(
    // Serialization groups: ['bookmark:show:public']
    // Validation groups: ['Default']
    schema: 'BookmarkShowPublic',
    description: 'Public bookmark information',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Bookmark ID'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp'),
        new OA\Property(property: 'title', type: 'string', description: 'Bookmark title'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', description: 'Bookmark URL'),
        new OA\Property(property: 'domain', type: 'string', description: 'Extracted domain from URL'),
        new OA\Property(property: 'account', type: 'object', description: 'Account that owns the bookmark', ref: '#/components/schemas/AccountShowPublic'),
        new OA\Property(property: 'tags', type: 'array', description: 'Associated public tags', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'mainImage', type: 'object', nullable: true, description: 'Main image file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'archive', type: 'object', nullable: true, description: 'Archive file object', ref: '#/components/schemas/FileObject'),
        new OA\Property(property: 'instance', type: 'string', description: 'Instance host where the bookmark was created'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the bookmark resource'),
    ]
)]
#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTime::ATOM])]
#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
#[ORM\Index(name: 'domain_idx', fields: ['domain'])]
#[ORM\Index(name: 'instance_idx', fields: ['instance'])]
class Bookmark
{
    public const string EXAMPLE_BOOKMARK_ID = '01234567-89ab-cdef-0123-456789abcdef';
    public const string EXAMPLE_BOOKMARK_IRI = 'https://bookmarkhive.test/users/me/bookmarks/' . self::EXAMPLE_BOOKMARK_ID;

    #[Groups(['bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public string $id;

    #[Groups(['bookmark:show:private', 'bookmark:show:public'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['bookmark:show:public', 'bookmark:create', 'bookmark:update', 'bookmark:show:private'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    #[ORM\Column(type: Types::TEXT)]
    public string $title;

    #[Groups(['bookmark:show:public', 'bookmark:create', 'bookmark:show:private'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    #[ORM\Column(type: Types::TEXT)]
    public string $url {
        set {
            $this->url = $value;
            $this->normalizedUrl = UrlHelper::normalize($value);
            $this->domain = UrlHelper::calculateDomain($value);
        }
    }

    #[Groups(['bookmark:show:public', 'bookmark:create', 'bookmark:show:private'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $mainImage = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Account $account;

    #[Groups(['bookmark:create', 'bookmark:show:private'])]
    #[ORM\Column]
    public bool $isPublic = false;

    #[Groups(['bookmark:show:public', 'bookmark:show:private'])]
    #[ORM\Column]
    public string $domain;

    #[ORM\Column(type: Types::TEXT)]
    public string $normalizedUrl;

    /** @var Collection<int, Tag>|array<int, Tag> */
    #[Groups(['bookmark:show:private', 'bookmark:show:public', 'bookmark:create', 'bookmark:update'])]
    #[ORM\ManyToMany(targetEntity: Tag::class, fetch: 'EAGER')]
    public Collection|array $tags;

    #[ORM\Column]
    public bool $outdated = false;

    #[Groups(['bookmark:show:private', 'bookmark:show:public', 'bookmark:create'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $archive = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private'])]
    #[ORM\Column]
    public string $instance;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        // We do not set tags here, so it can be unset when used as RequestPayload
        // Still we will set it to empty in BookmarkNormalizer if needed
        // $this->tags = new ArrayCollection();
    }

    /**
     * Given a list of tags, add those to the current object preventing doubloon.
     *
     * @param array<int, Tag>|Collection<int, Tag> $tags
     */
    public function mergeTags(array|Collection $tags): static
    {
        if (!isset($this->tags)) {
            $this->tags = new ArrayCollection();
        }

        if (!($this->tags instanceof Collection)) {
            $this->tags = new ArrayCollection($this->tags);
        }

        foreach ($tags as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }

        return $this;
    }
}
