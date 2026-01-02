<?php

namespace App\Entity;

use App\Helper\UrlHelper;
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
#[ORM\Index(name: 'domain_idx', fields: ['domain'])]
class Bookmark
{
    #[Groups(['bookmark:show:private', 'bookmark:show:public'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public string $id;

    #[Groups(['bookmark:show:private', 'bookmark:show:public'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[Groups(['bookmark:show:public', 'bookmark:create', 'bookmark:update', 'bookmark:show:private'])]
    #[Assert\NotBlank(groups: ['bookmark:create', 'bookmark:update'])]
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
    public User $owner;

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

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        // We do not set tags here, so it can be unset when used as RequestPayload
        // Still we will set it to empty in BookmarkNormalizer if needed
        // $this->tags = new ArrayCollection();
    }
}
