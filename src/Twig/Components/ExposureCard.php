<?php

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
    public int $percentage;
    public float $used;
    public float $remaining;
}
