<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[OA\Schema(
    // Serialization groups: ['account:show:public']
    // Validation groups: ['Default']
    schema: 'AccountShowPublic',
    description: 'Account object representing an ActivityPub account',
    type: 'object',
    properties: [
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the account resource'),
        new OA\Property(property: 'username', type: 'string', description: 'Account username'),
        new OA\Property(property: 'instance', type: 'string', description: 'Instance domain where the account is hosted'),
    ]
)]
#[UniqueEntity('uri')]
#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    /**
     * @var array<string, string>
     */
    public const array EXAMPLE_ACCOUNT = [
        '@iri' => 'https://bookmarkhive.test/profile/johndoe',
        'username' => 'johndoe',
        'instance' => 'bookmarkhive.test',
    ];

    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[ORM\OneToOne(inversedBy: 'account')]
    public ?User $owner = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $publicKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $privateKey = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:public', 'user:show:private', 'account:show:public'])]
    #[SerializedName('@iri')]
    #[ORM\Column(type: Types::TEXT)]
    public string $uri;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:public', 'user:show:private', 'account:show:public'])]
    #[ORM\Column]
    public string $username;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:public', 'user:show:private', 'account:show:public'])]
    #[ORM\Column]
    public string $instance;

    public \DateTimeImmutable $createdAt {
        get => new UuidV7($this->id)->getDateTime();
    }

    #[ORM\Column]
    public ?\DateTimeImmutable $lastUpdatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }
}
