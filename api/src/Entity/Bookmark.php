<?php

namespace App\Entity;

use App\Repository\BookmarkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Validator\Constraints as Assert;

#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTime::ATOM])]
#[ORM\Entity(repositoryClass: BookmarkRepository::class)]
class Bookmark
{
    #[Groups(['bookmark:owner', 'bookmark:profile', /*temp*/ 'bookmark:create'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public /*private(set)*/ string $id;

    #[Groups(['bookmark:owner', 'bookmark:profile'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    #[ORM\Column(type: Types::TEXT)]
    public string $title;

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner'])]
    #[Assert\NotBlank(groups: ['bookmark:create'])]
    #[ORM\Column(type: Types::TEXT)]
    public string $url {
        set {
            $this->url = $value;
            $this->domain = self::calculateDomain($value);
        }
    }

    #[Groups(['bookmark:profile', 'bookmark:create', 'bookmark:owner'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $mainImage = null;

    #[Groups(['bookmark:profile', 'bookmark:owner'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Groups(['bookmark:create', 'bookmark:owner'])]
    #[ORM\Column]
    public bool $isPublic = false;

    #[Groups(['bookmark:profile', 'bookmark:owner'])]
    #[ORM\Column]
    public string $domain;

    /** @var Collection<int, Tag>|array<int, Tag> */
    #[Groups(['bookmark:owner', 'bookmark:profile', 'bookmark:create'])]
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    public Collection|array $tags;

    #[ORM\Column]
    public bool $outdated = false;

    #[Groups(['bookmark:owner', 'bookmark:profile', 'bookmark:create'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $archive = null;

    #[Groups(['bookmark:owner', 'bookmark:profile'])]
    #[ORM\ManyToOne(targetEntity: FileObject::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?FileObject $pdf = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        $this->tags = new ArrayCollection();
    }

    /**
     * Opinionated: remove `www` and `m` (for mobile most of the time) from domain to normalize a bit.
     */
    private static function calculateDomain(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if (!$host) {
            return '';
        }

        return (string) preg_replace('`^(www|m)\.`', '', $host);
    }
}
