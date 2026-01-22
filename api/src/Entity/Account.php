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
        new OA\Property(property: 'inboxUrl', type: 'string', format: 'uri', nullable: true, description: 'ActivityPub inbox URL'),
        new OA\Property(property: 'outboxUrl', type: 'string', format: 'uri', nullable: true, description: 'ActivityPub outbox URL'),
        new OA\Property(property: 'sharedInboxUrl', type: 'string', format: 'uri', nullable: true, description: 'ActivityPub shared inbox URL'),
        new OA\Property(property: 'followerUrl', type: 'string', format: 'uri', nullable: true, description: 'ActivityPub followers collection URL'),
        new OA\Property(property: 'followingUrl', type: 'string', format: 'uri', nullable: true, description: 'ActivityPub following collection URL'),
    ]
)]
#[UniqueEntity('uri')]
#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    // https://regex101.com/r/ZwW3p1/2 TODO at some point we should allow more domain characters (same goes with username)
    public const string USERNAME_REGEX = '@?([a-zA-Z0-9_-]+)';
    public const string HOST_REGEX = '@([a-zA-Z0-9_.-]+)';
    public const string ACCOUNT_REGEX = '^' . self::USERNAME_REGEX . '(?:' . self::HOST_REGEX . ')?$';

    /**
     * @var array<string, string|null>
     */
    public const array EXAMPLE_ACCOUNT = [
        '@iri' => 'https://hivecache.test/profile/janedoe',
        'username' => 'janedoe',
        'instance' => 'hivecache.test',
        'inboxUrl' => 'https://hivecache.test/profile/janedoe/inbox',
        'outboxUrl' => 'https://hivecache.test/profile/janedoe/outbox',
        'sharedInboxUrl' => 'https://hivecache.test/inbox',
        'followerUrl' => 'https://hivecache.test/profile/janedoe/followers',
        'followingUrl' => 'https://hivecache.test/profile/janedoe/following',
    ];

    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[ORM\OneToOne(inversedBy: 'account')]
    public ?User $owner = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $publicKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $privateKey = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[SerializedName('@iri')]
    #[ORM\Column(type: Types::TEXT)]
    public string $uri;

    public string $keyId {
        get => $this->uri . '#main-key';
    }

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column]
    public string $username;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column]
    public string $instance;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $inboxUrl = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $outboxUrl = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $sharedInboxUrl = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $followerUrl = null;

    #[Groups(['bookmark:show:public', 'bookmark:show:private', 'user:show:private', 'account:show:public'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $followingUrl = null;

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
