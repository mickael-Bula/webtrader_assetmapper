<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\MarketData\CacLvcQuoteDto;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** @noinspection PhpUnused */
#[AsTwigComponent]
class StockMarketTable
{
    /**
     * @var array<CacLvcQuoteDto> Les données brutes du CAC
     */
    public array $quotes = [];

    /**
     * Formate n'importe quel nombre au format financier français.
     */
    public function format(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}
