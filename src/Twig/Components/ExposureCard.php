<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
final class ExposureCard
{
    public Chart $chart;
    public float $percentage;
    public float $used;
    public float $remaining;
    public string $exposureColor;
    public string $exposureStatus;
}
