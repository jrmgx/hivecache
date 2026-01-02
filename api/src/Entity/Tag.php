<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTime::ATOM])]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_owner_slug', fields: ['owner', 'slug'])]
class Tag
{
    #[Ignore]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['tag:show:public', 'tag:create', 'tag:show:private', 'tag:update'])]
    #[Assert\NotBlank(groups: ['tag:create', 'tag:update'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = self::slugger($value);
        }
    }

    #[Groups(['tag:show:public', 'tag:show:private'])]
    #[Assert\NotBlank(groups: ['tag:create', 'tag:update'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public private(set) string $slug;

    #[Groups(['tag:show:private'])]
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
        $slugger = new AsciiSlugger('en')->withEmoji();

        return mb_strtolower($slugger->slug($name));
    }
}
