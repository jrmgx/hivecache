<?php

namespace App\Entity;

use App\Api\Enum\FollowStatus;
use App\Repository\FollowingRepository;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    // Serialization groups: ['follower:show:public']
    // Validation groups: ['Default']
    schema: 'FollowerShowPublic',
    description: 'Follower object representing an account that follows the user',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Follower relationship ID'),
        new OA\Property(property: 'account', type: 'object', description: 'Account that follows the user', ref: '#/components/schemas/AccountShowPublic'),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'When the follow relationship was created'),
    ]
)]
#[ORM\Entity(repositoryClass: FollowingRepository::class)]
class Follower
{
    #[Groups(['follower:show:public'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['follower:show:public'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public Account $account;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner;

    #[Groups(['follower:show:public'])]
    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[ORM\Column]
    public FollowStatus $status = FollowStatus::Confirmed;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }
}
