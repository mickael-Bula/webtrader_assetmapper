<?php

declare(strict_types=1);

namespace App\Dto\MarketData;

final readonly class CacLvcQuoteDto
{
    public function __construct(
        private int                $id,
        private \DateTimeImmutable $date,
        private float              $cacClose,
        private ?float             $open,
        private ?float             $high,
        private ?float             $low,
        private ?float             $lvcClose,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getCacClose(): float
    {
        return $this->cacClose;
    }

    public function getLvcClose(): ?float
    {
        return $this->lvcClose;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getOpen(): ?float
    {
        return $this->open;

    }

    public function getHigh(): ?float
    {
        return $this->high;
    }

    public function getLow(): ?float
    {
        return $this->low;
    }

    public function getFormattedDate(): string
    {
        return $this->date->format('d/m/Y');
    }
}
