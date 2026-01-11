<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    // Serialization groups: ['tag:show:private']
    // Validation groups: ['Default']
    schema: 'TagShowPrivate',
    description: 'Tag object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'Tag name'),
        new OA\Property(property: 'slug', type: 'string', description: 'Tag slug'),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the tag is public'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the tag resource'),
    ]
)]
#[OA\Schema(
    // Serialization groups: ['tag:show:public']
    // Validation groups: ['Default']
    schema: 'TagShowPublic',
    description: 'Public tag information',
    type: 'object',
    properties: [
        new OA\Property(property: 'name', type: 'string', description: 'Tag name'),
        new OA\Property(property: 'slug', type: 'string', description: 'Tag slug'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the tag resource'),
    ]
)]
#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTime::ATOM])]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_owner_slug', fields: ['owner', 'slug'])]
class Tag
{
    /**
     * @var array<string, string>
     */
    public const array EXAMPLE_TAG = [
        'name' => 'Bookmarking',
        'slug' => 'bookmarking',
        '@iri' => 'https://bookmarkhive.test/users/me/tags/bookmarking',
    ];

    #[Ignore]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['tag:show:public', 'tag:create', 'tag:show:private', 'tag:update'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = self::slugger($value);
        }
    }

    #[Groups(['tag:show:public', 'tag:show:private'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public private(set) string $slug;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    /** @var array<string, string> */
    #[Groups(['tag:create', 'tag:show:private', 'tag:update'])]
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    public array $meta = [];

    #[Groups(['tag:create', 'tag:show:private', 'tag:update'])]
    #[ORM\Column]
    public bool $isPublic = false;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }

    private static function slugger(string $name): string
    {
        $slugger = new AsciiSlugger('en');

        return mb_strtolower($slugger->slug($name));
    }
}
