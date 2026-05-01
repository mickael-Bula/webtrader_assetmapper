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

        $totalCapital = (float)$user->getTotalPortfolio();
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
            $percentage >= 75 => '#ff4b5c', // Rouge
            $percentage >= 50 => '#ffa502', // Orange
            $percentage >= 25 => '#198754', // Vert
            default => '#36a2eb',           // Bleu
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

    /**
     * Regroupe les positions pour un affichage par ligne, présentant la quantité et le PRU de chaque position.
     */
    public function getGroupedPositions(User $user): array
    {
        $positions = $this->positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user);
        $grouped = [];

        foreach ($positions as $position) {
            $name = 'LVC';

            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'total_quantity' => 0,
                    'total_cost' => 0,
                    'current_price' => (float)$position->getCurrentPrice(),
                    'total_current_value' => 0,
                ];
            }

            $quantity = (float)$position->getQuantity();
            $buyPrice = (float)$position->getLvcBuyPrice();

            $grouped[$name]['total_quantity'] += $quantity;
            $grouped[$name]['total_cost'] += ($quantity * $buyPrice);
            $grouped[$name]['total_current_value'] += ($quantity * (float)$position->getCurrentPrice());
        }

        // Calcul final du PRU et de la performance pour chaque groupe
        foreach ($grouped as $name => &$data) {
            $data['pru'] = $data['total_cost'] / $data['total_quantity'];
            $data['pnl_euro'] = $data['total_current_value'] - $data['total_cost'];
            $data['pnl_percent'] = ($data['pnl_euro'] / $data['total_cost']) * 100;
        }

        return $grouped;
    }
}
