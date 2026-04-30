<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class PortfolioService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder
    )
    {
    }

    public function getExposureData(User $user, array $runningPositions): array
    {
        $totalCapital = (float)$user->getTotalPortfolio();
        $usedCapital = 0;

        foreach ($runningPositions as $pos) {
            // Calcul de la valeur engagée (Quantité * Prix d'achat LVC)
            $usedCapital += ($pos->getQuantity() * $pos->getLvcBuyPrice());
        }

        $remainingCapital = max(0, $totalCapital - $usedCapital);
        $percentage = $totalCapital > 0 ? round(($usedCapital / $totalCapital) * 100) : 0;

        return [
            'chart' => $this->createExposureChart($usedCapital, $remainingCapital, $percentage),
            'percentage' => $percentage,
            'used' => $usedCapital,
            'remaining' => $remainingCapital,
        ];
    }

    private function createExposureChart(float $used, float $remaining, float $percentage): Chart
    {
        // Logique de couleur dynamique
        $color = match (true) {
            $percentage >= 90 => '#ff4b5c', // Rouge (Alerte)
            $percentage >= 70 => '#ffa502', // Orange (Prudence)
            default => '#36a2eb',           // Bleu (Normal)
        };

        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart->setData([
                            'labels' => ['Occupé', 'Libre'],
                            'datasets' => [[
                                'backgroundColor' => [$color, '#2c2c2c'],
                                'borderColor' => 'transparent',
                                'data' => [$used, $remaining],
                                'cutout' => '80%',
                            ]],
                        ]);

        $chart->setOptions([
                               'responsive' => true,
                               'maintainAspectRatio' => false, // Crucial pour contrôler la taille en CSS
                               'plugins' => [
                                   'legend' => ['display' => false],
                                   'tooltip' => ['enabled' => false],
                               ],
                               'interaction' => [
                                   'intersect' => true,
                               ],
                               'cutout' => '80%', // Un anneau plus fin fait paraître le graphique plus petit/élégant
                           ]);

        return $chart;
    }
}
