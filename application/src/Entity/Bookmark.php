<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Processor\BookmarkMeProcessor;
use App\Provider\UserMeBookmarkProvider;
use App\Provider\UserProfileBookmarkProvider;
use App\Repository\BookmarkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    uriTemplate: '/users/me/bookmarks',
    operations: [
        new GetCollection(
            description: 'List own bookmarks',
            provider: UserMeBookmarkProvider::class,
            normalizationContext: ['groups' => ['bookmark:owner', 'tag:owner']],
            parameters: [
                'tags' => new QueryParameter(property: 'hydra:freetextQuery'),
            ]
        ),
        new Post(
            description: 'Create new bookmark',
            processor: BookmarkMeProcessor::class,
            denormalizationContext: ['groups' => ['bookmark:create']],
            normalizationContext: ['groups' => ['bookmark:owner', 'tag:owner']],
            validationContext: ['groups' => ['Default']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/users/me/bookmarks/{id}',
    uriVariables: [
        'id' => new Link(fromClass: Bookmark::class),
    ],
    operations: [
        new Get(
            description: 'Get own bookmark',
            security: 'object.owner == user',
            provider: UserMeBookmarkProvider::class,
            normalizationContext: ['groups' => ['bookmark:owner', 'tag:owner']],
        ),
        new Patch(
            description: 'Edit own bookmark',
            security: 'object.owner == user',
            provider: UserMeBookmarkProvider::class,
            denormalizationContext: ['groups' => ['bookmark:owner']],
            normalizationContext: ['groups' => ['bookmark:owner']],
            validationContext: ['groups' => ['Default']],
        ),
        new Delete(
            description: 'Delete own bookmark',
            security: 'object.owner == user',
            provider: UserMeBookmarkProvider::class,
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/profile/{username}/bookmarks',
    uriVariables: [
        'username' => new Link(fromClass: User::class, fromProperty: 'bookmarks'),
    ],
    operations: [
        new GetCollection(
            // Security is handled in the provider
            provider: UserProfileBookmarkProvider::class,
            description: 'Public bookmarks of user',
            normalizationContext: ['groups' => ['bookmark:profile', 'tag:profile']],
            parameters: [
                'tags' => new QueryParameter(property: 'hydra:freetextQuery'),
            ],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/profile/{username}/bookmarks/{id}',
    uriVariables: [
        'username' => new Link(fromClass: User::class, fromProperty: 'bookmarks'),
        'id' => new Link(fromClass: Bookmark::class),
    ],
    operations: [
        new Get(
            // Security is handled in the provider
            provider: UserProfileBookmarkProvider::class,
            description: 'Show given public bookmark',
            normalizationContext: ['groups' => ['bookmark:profile', 'tag:profile']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
class Bookmark
{
    #[Groups(['bookmark:admin', 'bookmark:owner', 'bookmark:profile'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['bookmark:admin', 'bookmark:owner', 'bookmark:profile'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner', 'bookmark:admin'])]
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    public string $title;

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner', 'bookmark:admin'])]
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    public string $url;

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner', 'bookmark:admin'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $mainImage = null;

    #[Groups(['bookmark:owner', 'bookmark:admin'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Groups(['bookmark:create', 'bookmark:owner', 'bookmark:admin'])]
    #[ORM\Column]
    public bool $isPublic = false;

    /**
     * @var Collection<int, Tag>|array<int, Tag>
     */
    #[Groups(['bookmark:admin', 'bookmark:owner', 'bookmark:profile', 'bookmark:create'])]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public Collection|array $tags;

    #[ORM\Column]
    public bool $outdated = false;

    #[Groups(['bookmark:admin', 'bookmark:owner', 'bookmark:profile', 'bookmark:create'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $archive = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        $this->tags = new ArrayCollection();
    }
}
