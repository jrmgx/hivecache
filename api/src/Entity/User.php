<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    // Serialization groups: ['user:show:private']
    // Validation groups: ['Default']
    schema: 'UserShowPrivate',
    description: 'User object with owner-level details',
    type: 'object',
    properties: [
        new OA\Property(property: 'username', type: 'string', description: 'Username'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public'),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
        new OA\Property(property: 'account', type: 'object', description: 'Account associated with the user', ref: '#/components/schemas/AccountShowPublic'),
        new OA\Property(property: '@iri', type: 'string', format: 'iri', description: 'IRI of the user resource'),
    ]
)]
#[OA\Schema(
    // Serialization groups: ['user:create']
    // Validation groups: ['Default', 'user:create']
    schema: 'UserCreate',
    description: 'User creation data',
    type: 'object',
    required: ['username', 'password'],
    properties: [
        new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 32, description: 'Username'),
        new OA\Property(property: 'password', type: 'string', minLength: 8, description: 'Password'),
        new OA\Property(property: 'isPublic', type: 'boolean', description: 'Whether the profile is public', default: false),
        new OA\Property(property: 'meta', type: 'object', description: 'Additional metadata', additionalProperties: true),
    ]
)]
#[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
#[UniqueEntity('username', groups: ['user:create', 'user:update'])]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string EXAMPLE_USER_IRI = 'https://hivecache.test/users/me';

    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['user:create', 'user:show:private', 'bookmark:show:public', 'bookmark:show:private', 'user:update'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 3, max: 32)]
    #[Assert\Regex(pattern: '`^' . Account::USERNAME_REGEX . '$`')] // TODO document in Open API
    #[ORM\Column(length: 32, unique: true)]
    public string $username {
        set {
            $this->username = mb_strtolower($value);
        }
    }

    #[Groups(['user:create', 'user:show:private', 'user:update'])]
    #[ORM\Column]
    public bool $isPublic = false;

    /** @var array<string, string> */
    #[Groups(['user:create', 'user:show:private', 'user:update'])]
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    public array $meta = [];

    /**
     * This field is used to validate the JWT so changing it allows to invalidate the JWT.
     * It is useful when the user changes its password or for other security means.
     */
    #[ORM\Column(options: ['default' => 'initial'])]
    public private(set) string $securityInvalidation = 'initial';

    #[Groups(['user:show:private'])]
    #[ORM\OneToOne(mappedBy: 'owner')]
    public Account $account {
        set {
            $this->account = $value;
            $value->owner = $this;
        }
    }

    #[Ignore]
    #[ORM\Column]
    private string $password;

    #[SerializedName('password')]
    #[Groups(['user:create', 'user:show:private', 'user:update'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 8)]
    private ?string $plainPassword = null;

    /** @var array<int, string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param array<int, string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->username) {
            throw new \LogicException('Username can not be empty.');
        }

        return $this->username;
    }

    public function rotateSecurity(): self
    {
        $this->securityInvalidation = hash('sha256', uniqid(more_entropy: true));

        return $this;
    }

    /**
     * @see UserInterface
     *
     * Required until Symfony 8.0, where eraseCredentials() will be removed from the interface.
     * No-op since plainPassword is cleared manually in the password processor.
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Intentionally left blank
    }
}
