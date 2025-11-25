<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Processor\UserMeProcessor;
use App\Processor\UserMeRemoveProcessor;
use App\Provider\UserByUsernameProvider;
use App\Provider\UserMeProvider;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    uriTemplate: '/users/me',
    operations: [
        new Get(
            description: 'Get own profile',
            provider: UserMeProvider::class,
        ),
        new Patch(
            description: 'Update own profile',
            processor: UserMeProcessor::class,
            validationContext: ['groups' => ['Default', 'user:update']],
            denormalizationContext: ['groups' => ['user:owner']],
        ),
        new Delete(
            description: 'Delete own profile',
            processor: UserMeRemoveProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['user:owner']],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    uriTemplate: '/profile/{username}',
    uriVariables: [
        'username' => new Link(fromClass: User::class),
    ],
    operations: [
        new Get(
            description: 'Show public profile',
            provider: UserByUsernameProvider::class,
            security: 'object.isPublic',
            normalizationContext: ['groups' => ['user:profile']],
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[ApiResource(
    // uriTemplate: '/users/{id}',
    operations: [
        new Post(
            description: 'Register a new account',
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserMeProcessor::class,
            denormalizationContext: ['groups' => ['user:create']],
            normalizationContext: ['groups' => ['user:owner']],
        ),
        new GetCollection(
            description: 'Admin: List all users',
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("ROLE_ADMIN")',
            normalizationContext: ['groups' => ['user:admin']],
        ),
        new Patch(
            description: 'Admin: Edit user',
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("ROLE_ADMIN")',
            validationContext: ['groups' => ['Default', 'user:update']],
            normalizationContext: ['groups' => ['user:admin']],
            denormalizationContext: ['groups' => ['user:admin']],
        ),
        new Delete(
            description: 'Admin: Delete user',
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("ROLE_ADMIN")'
        ),
    ],
    collectDenormalizationErrors: true,
)]
#[UniqueEntity('email')]
#[UniqueEntity('username')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[Groups(['user:admin', 'user:owner'])]
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Groups(['user:create', 'user:owner', 'user:admin'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Email]
    #[ORM\Column(length: 180, unique: true)]
    public string $email;

    #[Groups(['user:profile', 'user:create', 'user:owner', 'user:admin'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    #[Assert\Length(min: 3, max: 32)]
    #[ORM\Column(length: 32, unique: true)]
    public string $username;

    #[Groups(['user:create', 'user:owner', 'user:admin'])]
    #[ORM\Column]
    public bool $isPublic = false;

    #[ORM\Column]
    private string $password;

    #[Groups(['user:create', 'user:owner', 'user:admin'])]
    #[Assert\NotBlank(groups: ['user:create'])]
    private ?string $plainPassword = null;

    /**
     * @var array<int, string>
     */
    #[Groups(['user:admin'])]
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    //    /**
    //     * @var Collection<int, Bookmark>
    //     */
    //    #[ORM\OneToMany(targetEntity: Bookmark::class, mappedBy: 'owner', orphanRemoval: true)]
    //    private Collection $bookmarks;
    //
    //    /**
    //     * @var Collection<int, Tag>
    //     */
    //    #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'owner', orphanRemoval: true)]
    //    private Collection $tags;

    public function __construct()
    {
        $this->id = Uuid::v7()->toString();
        // $this->bookmarks = new ArrayCollection();
        // $this->tags = new ArrayCollection();
    }

    //    /**
    //     * @return Collection<int, Bookmark>
    //     */
    //    public function getBookmarks(): Collection
    //    {
    //        throw new \LogicException('We dont want to call that method.');
    //        // return $this->bookmarks;
    //    }
    //
    //    /**
    //     * @return Collection<int, Tag>
    //     */
    //    public function getTags(): Collection
    //    {
    //        throw new \LogicException('We dont want to call that method.');
    //        // return $this->bookmarks;
    //    }

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
     * @see UserInterface
     *
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
     * @see UserInterface
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('Email can not be empty.');
        }

        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * Required until Symfony 8.0, where eraseCredentials() will be removed from the interface.
     * No-op since plainPassword is cleared manually in the password processor.
     */
    public function eraseCredentials(): void
    {
        // Intentionally left blank
    }
}
