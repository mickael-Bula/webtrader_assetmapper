<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class ChartService
{
    public function __construct(private ChartBuilderInterface $chartBuilder)
    {
    }

    /**
     * Retourne un graphique de performance du portefeuille.
     *
     * @param array<int, string|null>    $labels
     * @param array<int, float|int|null> $data
     */
    public function getPerformanceChart(array $labels, array $data): Chart
    {
        // Création du graphique
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Valeur du portefeuille (€)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                    'borderColor' => '#36a2eb',
                    'data' => $data,
                    'fill' => true,
                    'tension' => 0.3, // Pour une courbe lisse et moderne
                    'pointRadius' => 2,
                ],
            ],
        ]);

        $chart->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false], // On cache la légende pour épurer
            ],
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(255, 255, 255, 0.05)'],
                    'ticks' => ['color' => '#6c757d'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#6c757d'],
                ],
            ],
        ]);

        return $chart;
    }
}
