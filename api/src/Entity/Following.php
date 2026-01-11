<?php

namespace App\Entity;

use App\Enum\FollowStatus;
use App\Repository\FollowingRepository;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    // Serialization groups: ['following:show:public']
    // Validation groups: ['Default']
    schema: 'FollowingShowPublic',
    description: 'Following object representing an account that the user follows',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Following relationship ID'),
        new OA\Property(property: 'account', type: 'object', description: 'Account that is being followed', ref: '#/components/schemas/AccountShowPublic'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'When the follow relationship was created'),
    ]
)]
#[ORM\Entity(repositoryClass: FollowingRepository::class)]
class Following
{
    #[Groups(['following:show:public'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Groups(['following:show:public'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Account $account;

    #[Groups(['following:show:public'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[ORM\Column]
    public FollowStatus $status = FollowStatus::Pending;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
