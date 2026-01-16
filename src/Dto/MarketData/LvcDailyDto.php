<?php

declare(strict_types=1);

namespace App\Dto\MarketData;

final readonly class LvcDailyDto
{
    public function __construct(
        private int                $id,
        private \DateTimeInterface $date,
        private float              $open,
        private float              $high,
        private float              $low,
        private float              $close
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getClose(): float
    {
        return $this->close;
    }

    public function getOpen(): float
    {
        return $this->open;
    }

    public function getHigh(): float
    {
        return $this->high;
    }

    public function getLow(): float
    {
        return $this->low;
    }
}
