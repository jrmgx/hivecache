<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity('email')]
#[ORM\Entity(repositoryClass: AdminRepository::class)]
class Admin implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\Column(type: 'uuid')]
    public private(set) string $id;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[ORM\Column(unique: true)]
    public string $email {
        set {
            $this->email = mb_strtolower($value);
        }
    }

    #[ORM\Column]
    private string $password;

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
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Intentionally left blank
    }
}
