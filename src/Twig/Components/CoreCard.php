<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
final class CoreCard
{
    public float $totalQuantity;
    public float $targetValue;
    public float $pru;
    public float $currentValue;
    public float $progressPercent;
    public float $performancePercent;
}
