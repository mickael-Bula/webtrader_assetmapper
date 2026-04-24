<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** @noinspection PhpUnused */
#[AsTwigComponent]
class Calculator
{
    /** @noinspection PhpUnused */
    public float $lastQuote = 0.0;
}
