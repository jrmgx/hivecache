<?php

namespace App\Entity;

use App\Repository\UserTimelineEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: UserTimelineEntryRepository::class)]
class UserTimelineEntry
{
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public string $id;

    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Bookmark $bookmark;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
