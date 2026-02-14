<?php

namespace App\Entity;

use App\Api\InstanceTagService;
use App\Repository\InstanceTagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
#[ORM\Entity(repositoryClass: InstanceTagRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_slug', fields: ['slug'])]
class InstanceTag
{
    #[Ignore]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['tag:show:public'])]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public string $name {
        set {
            $this->name = $value;
            $this->slug = InstanceTagService::slugger($value);
        }
    }

    #[Groups(['tag:show:public'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[ORM\Column(length: 32)]
    public private(set) string $slug;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
