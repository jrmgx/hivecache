<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Processor\TagMeProcessor;
use App\Provider\UserMeTagProvider;
use App\Provider\UserProfileTagProvider;
use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    uriTemplate: '/users/me/tags',
    operations: [
        new GetCollection(
            description: 'List own tags',
            provider: UserMeTagProvider::class,
            normalizationContext: ['groups' => ['tag:owner']],
        ),
        new Post(
            description: 'Create new tag',
            processor: TagMeProcessor::class,
            denormalizationContext: ['groups' => ['tag:create']],
            normalizationContext: ['groups' => ['tag:owner']],
            validationContext: ['groups' => ['Default']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/users/me/tags/{slug}',
    // TODO not sure about that
    uriVariables: [
        'slug' => new Link(fromClass: Tag::class),
    ],
    operations: [
        new Get(
            description: 'Get own tag',
            security: 'object.owner == user',
            provider: UserMeTagProvider::class,
            normalizationContext: ['groups' => ['tag:owner']],
        ),
        new Patch(
            description: 'Edit own tag',
            security: 'object.owner == user',
            provider: UserMeTagProvider::class,
            denormalizationContext: ['groups' => ['tag:owner']],
            normalizationContext: ['groups' => ['tag:owner']],
            validationContext: ['groups' => ['Default']],
        ),
        new Delete(
            description: 'Delete own tag',
            security: 'object.owner == user',
            provider: UserMeTagProvider::class,
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/profile/{username}/tags',
    uriVariables: [
        'username' => new Link(fromClass: User::class, fromProperty: 'tags'),
    ],
    operations: [
        new GetCollection(
            // Security is handled in the provider
            provider: UserProfileTagProvider::class,
            description: 'Public tags of user',
            normalizationContext: ['groups' => ['tag:profile']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/profile/{username}/tags/{slug}',
    uriVariables: [
        'username' => new Link(fromClass: User::class, fromProperty: 'tags'),
        'slug' => new Link(fromClass: Tag::class), // TODO not sure about that
    ],
    operations: [
        new Get(
            // Security is handled in the provider
            provider: UserProfileTagProvider::class,
            description: 'Show given public tag',
            normalizationContext: ['groups' => ['tag:profile']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[Groups(['tag:admin', 'tag:owner', 'tag:profile'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['tag:profile', 'tag:create', 'tag:owner', 'tag:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = self::slugger($value);
        }
    }

    #[Groups(['tag:profile', 'tag:owner', 'tag:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public private(set) string $slug;

    #[Groups(['tag:owner', 'tag:admin'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Groups(['tag:create', 'tag:owner', 'tag:admin'])]
    #[ORM\Column]
    public bool $isPublic = false;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }

    public static function slugger(string $name): string
    {
        $slugger = new AsciiSlugger('en');

        return strtolower($slugger->slug($name));
    }
}
