<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PositionStatus;
use App\Repository\EntrypointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: EntrypointRepository::class)]
#[ORM\Table(name: 'entrypoint')]
class Entrypoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'entrypoints')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $entrypoint = null; // Seuil d'activation d'un cycle d'achat

    #[ORM\Column(type: 'string', enumType: PositionStatus::class)]
    private PositionStatus $status = PositionStatus::WAITING; // waiting, running, closed

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: Position::class, mappedBy: 'entrypoint', orphanRemoval: true)]
    private Collection $positions;

    public function __construct() {
        $this->createdAt = new \DateTimeImmutable();
        $this->positions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getEntrypoint(): ?string
    {
        return $this->entrypoint;
    }

    public function setEntrypoint(?string $entrypoint): void
    {
        $this->entrypoint = $entrypoint;
    }

    public function getStatus(): PositionStatus
    {
        return $this->status;
    }

    public function setStatus(?PositionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return Collection<int, Position>
     */
    public function getPositions(): Collection
    {
        return $this->positions;
    }

    /**
     * Cette méthode vérifie si une position en cours existe pour cet entrypoint.
     */
    public function isLocked(): bool
    {
        foreach ($this->positions as $position) {
            if ($position->getStatus() === PositionStatus::RUNNING) {
                return true;
            }
        }

        return false;
    }

    public function addPosition(Position $position): self
    {
        if (!$this->positions->contains($position)) {
            $this->positions->add($position);
            $position->setEntrypoint($this);
        }

        return $this;
    }

    public function removePosition(Position $position): self
    {
        if ($this->positions->removeElement($position) && $position->getEntrypoint() === $this) {
            $position->setEntrypoint(null);
        }

        return $this;
    }
}
