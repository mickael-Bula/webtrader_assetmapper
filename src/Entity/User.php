<?php

namespace App\Entity;

use App\Enum\PositionStatus;
use Doctrine\DBAL\Types\Types;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var ?string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $lastCacUpdatedId = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $upperRange = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $buyLimit = null;

    #[ORM\OneToMany(targetEntity: Entrypoint::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $entrypoints;

    public function __construct()
    {
        $this->entrypoints = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getLastCacUpdatedId(): ?int
    {
        return $this->lastCacUpdatedId;
    }

    public function setLastCacUpdatedId(?int $id): static
    {
        $this->lastCacUpdatedId = $id;

        return $this;
    }

    public function getUpperRange(): ?string
    {
        return $this->upperRange;
    }

    public function setUpperRange(?string $upperRange): static
    {
        $this->upperRange = $upperRange;

        return $this;
    }

    public function getBuyLimit(): ?string
    {
        return $this->buyLimit;
    }

    public function setBuyLimit(?string $buyLimit): static
    {
        $this->buyLimit = $buyLimit;

        return $this;
    }

    /**
     * @return Collection<int, Entrypoint>
     */
    public function getEntrypoints(): Collection
    {
        return $this->entrypoints;
    }

    /**
     * Méthode retournant les entrypoints dont le statut n'est pas CLOSED.
     */
    public function getActiveEntrypoints(): array
    {
        $activeEntrypoints = [];
        foreach ($this->entrypoints as $entrypoint) {
            if ($entrypoint->getStatus() !== PositionStatus::CLOSED) {
                $activeEntrypoints[] = $entrypoint;
            }
        }

        return $activeEntrypoints;
    }

    /**
     * Récupère l'entrypoint avec le statut WAITING.
     */
    public function getWaitingEntrypoint(): ?Entrypoint
    {
        foreach ($this->entrypoints as $entrypoint) {
            if ($entrypoint->getStatus() === PositionStatus::WAITING) {
                return $entrypoint;
            }
        }

        return null;
    }
}
