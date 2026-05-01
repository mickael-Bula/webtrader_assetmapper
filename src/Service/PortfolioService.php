<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Position;
use App\Enum\PositionStatus;
use App\Repository\PositionRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class PortfolioService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private PositionRepository $positionRepository,
    )
    {
    }

    public function calculateCurrentSnapshot(User $user): array
    {
        $runningPositions = $this->positionRepository->findByStatusAndUser(
            PositionStatus::RUNNING,
            $user
        );

        // TODO : getTotalPortfolio ne récupère que le capital de base, sans les profits réalisés
        $totalCapital = (float)$user->getTotalPortfolio(); // capital de base + profits réalisés
        $unrealizedPnl = 0;

        foreach ($runningPositions as $pos) {
            // La valeur actuelle en bourse de la position
            $currentVal = $pos->getQuantity() * $pos->getCurrentPrice();
            $buyVal = $pos->getQuantity() * $pos->getLvcBuyPrice();

            $unrealizedPnl += ($currentVal - $buyVal);
        }

        // L'equity est le capital théorique si on vendait tout
        $totalEquity = $totalCapital + $unrealizedPnl;

        return [
            'total_equity' => $totalEquity,
            'cash_amount' => $totalCapital - $this->getEngagedCapital($runningPositions),
            'unrealized_pnl' => $unrealizedPnl
        ];
    }

    /**
     * Calcule le capital total actuellement investi (en €)
     *
     * @param Position[] $runningPositions
     */
    public function getEngagedCapital(array $runningPositions): float
    {
        $totalEngaged = 0.0;

        foreach ($runningPositions as $position) {
            // On vérifie que la quantité et le prix d'achat LVC sont renseignés
            if ($position->getQuantity() && $position->getLvcBuyPrice()) {
                $totalEngaged += ($position->getQuantity() * (float)$position->getLvcBuyPrice());
            }
        }

        return $totalEngaged;
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
