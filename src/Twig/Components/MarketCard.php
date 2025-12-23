<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** @noinspection PhpUnused */
#[AsTwigComponent]
class MarketCard
{
    public string $title;
    public float $value;
    public string $unit = 'pts';

    /** @noinspection PhpUnused */
    public ?string $subtitle = null;

    /** @noinspection PhpUnused */
    public ?string $trend = null;

    /**
     * @noinspection PhpUnused
     *
     * Méthode de formatage des nombres décimaux en style français.
     */
    public function getFormattedValue(): string
    {
        $formattedNumber = number_format($this->value, 2, ',', ' ');

        return $formattedNumber . ' ' . $this->unit;
    }
}
