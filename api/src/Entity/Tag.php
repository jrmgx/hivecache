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
class Tag
{
    #[Ignore]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['tag:profile', 'tag:create', 'tag:owner'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = self::slugger($value);
        }
    }

    #[Groups(['tag:profile', 'tag:owner'])]
    #[Assert\NotBlank(groups: ['tag:create'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public private(set) string $slug;

    #[Groups(['tag:owner'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    /** @var array<string, string> */
    #[Groups(['tag:create', 'tag:owner'])]
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    public array $meta = [];

    #[Groups(['tag:create', 'tag:owner'])]
    #[ORM\Column]
    public bool $isPublic = false;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }

    private static function slugger(string $name): string
    {
        $slugger = new AsciiSlugger('en')->withEmoji();

        return strtolower($slugger->slug($name));
    }
}
