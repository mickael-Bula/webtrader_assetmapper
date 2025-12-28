<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CacDailyRepository;

#[ORM\Entity(repositoryClass: CacDailyRepository::class)]
class CacDaily
{
    #[ORM\Id]
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'float')]
    private float $close;

    #[ORM\Column(type: 'float')]
    private float $open;

    #[ORM\Column(type: 'float')]
    private ?float $high = null;

    #[ORM\Column(type: 'float')]
    private ?float $low = null;

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(): static
    {
        $this->date = new \DateTimeImmutable();

        return $this;
    }

    public function getClose(): ?float
    {
        return $this->close;
    }

    public function setClose(float $close): static
    {
        $this->close = $close;

        return $this;
    }

    public function getOpen(): ?float
    {
        return $this->open;
    }

    public function setOpen(float $open): static
    {
        $this->open = $open;

        return $this;
    }

    public function getHigh(): ?float
    {
        return $this->high;
    }

    public function setHigh(float $high): static
    {
        $this->high = $high;

        return $this;
    }

    public function getLow(): ?float
    {
        return $this->low;
    }

    public function setLow(float $low): static
    {
        $this->low = $low;

        return $this;
    }
}
