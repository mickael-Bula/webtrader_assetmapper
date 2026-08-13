<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PortfolioSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortfolioSnapshotRepository::class)]
class PortfolioSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?float $totalEquity = null;

    #[ORM\Column]
    private ?float $cashAmount = null;

    #[ORM\Column]
    private ?float $realizedPnl = null;

    #[ORM\ManyToOne(inversedBy: 'portfolioSnapshots')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->realizedPnl = 0.0; // Évite les erreurs de contrainte NOT NULL
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getTotalEquity(): ?float
    {
        return $this->totalEquity;
    }

    public function setTotalEquity(float $totalEquity): static
    {
        $this->totalEquity = $totalEquity;

        return $this;
    }

    public function getCashAmount(): ?float
    {
        return $this->cashAmount;
    }

    public function setCashAmount(float $cashAmount): static
    {
        $this->cashAmount = $cashAmount;

        return $this;
    }

    public function getRealizedPnl(): ?float
    {
        return $this->realizedPnl;
    }

    public function setRealizedPnl(float $realizedPnl): static
    {
        $this->realizedPnl = $realizedPnl;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
