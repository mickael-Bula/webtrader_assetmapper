<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PositionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PositionRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PositionRepository::class)]
#[ORM\Table(name: 'position', schema: 'app')]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'positions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entrypoint $entrypoint = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 3)] // On limite à trois positions par cycle d'achat
    private int $rank;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $buyPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $targetPrice;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $lvcBuyPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $lvcTargetPrice = null;

    #[ORM\Column(type: 'string', enumType: PositionStatus::class)]
    private PositionStatus $status = PositionStatus::RUNNING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Calcule automatiquement le targetPrice à +10 % lors de la définition du prix d'achat
     */
    public function setBuyPrice(string $buyPrice): static
    {
        $this->buyPrice = $buyPrice;

        // On utilise (float) pour le calcul, puis on repasse en string pour le stockage decimal
        $target = (float)$buyPrice * 1.10;
        $this->targetPrice = (string)round($target, 2);

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntrypoint(): ?Entrypoint
    {
        return $this->entrypoint;
    }

    public function setEntrypoint(?Entrypoint $entrypoint): static
    {
        $this->entrypoint = $entrypoint;

        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getBuyPrice(): string
    {
        return $this->buyPrice;
    }

    public function getTargetPrice(): string
    {
        return $this->targetPrice;
    }

    public function setTargetPrice(string $targetPrice): static
    {
        $this->targetPrice = $targetPrice;

        return $this;
    }

    /**
     * Définit le prix d'achat LVC et calcule automatiquement
     * le prix de revente cible à +20 %
     */
    public function setLvcBuyPrice(?string $lvcBuyPrice): static
    {
        $this->lvcBuyPrice = $lvcBuyPrice;

        if (null === $lvcBuyPrice) {
            return $this;
        }

        $target = (float) $lvcBuyPrice * 1.20;
        $this->lvcTargetPrice = (string) round($target, 2);

        return $this;
    }

    public function getLvcBuyPrice(): ?string
    {
        return $this->lvcBuyPrice;
    }

    public function getLvcTargetPrice(): ?string
    {
        return $this->lvcTargetPrice;
    }

    public function setLvcTargetPrice(?string $lvcTargetPrice): static
    {
        $this->lvcTargetPrice = $lvcTargetPrice;

        return $this;
    }

    public function getStatus(): PositionStatus
    {
        return $this->status;
    }

    public function setStatus(PositionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
