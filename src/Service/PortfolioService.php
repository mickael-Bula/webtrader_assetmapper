<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Position;
use App\Entity\User;
use App\Enum\PositionStatus;
use App\Repository\PortfolioSnapshotRepository;
use App\Repository\PositionRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class PortfolioService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private PositionRepository $positionRepository,
        private PortfolioSnapshotRepository $snapshotRepo,
    ) {
    }

    public function calculateCurrentSnapshot(User $user): array
    {
        // Récupère toutes les positions en cours de l'utilisateur (Core + Trading).
        $runningPositions = $this->positionRepository->findByStatusAndUser(
            PositionStatus::RUNNING,
            $user
        );

        $totalCapital = (float) $user->getTotalPortfolio();
        $unrealizedPnl = 0;

        foreach ($runningPositions as $pos) {
            // La valeur actuelle en bourse de la position
            $currentVal = $pos->getQuantity() * $pos->getCurrentPrice();
            $buyVal = $pos->getQuantity() * $pos->getLvcBuyPrice();

            $unrealizedPnl += ($currentVal - $buyVal);
        }

        // L'equity est le capital théorique si on vendait tout
        $totalEquity = $totalCapital + $unrealizedPnl;
        $cashAmount = $totalCapital - $this->getEngagedCapital($runningPositions);
        $exposurePercent = $totalEquity > 0 ? (($totalEquity - $cashAmount) / $totalEquity) * 100 : 0;

        return [
            'total_equity' => $totalEquity,
            'cash_amount' => $totalCapital - $this->getEngagedCapital($runningPositions),
            'unrealized_pnl' => $unrealizedPnl,
            'exposure_percent' => $exposurePercent,
            'exposure_color' => $this->getExposureColor($exposurePercent),
            'exposure_label' => $this->getExposureLabel($exposurePercent),
            'exposure_description' => $this->getExposureDescription($exposurePercent),
        ];
    }

    /**
     * Calcule le capital total actuellement investi (en €).
     *
     * @param Position[] $runningPositions
     */
    public function getEngagedCapital(array $runningPositions): float
    {
        $totalEngaged = 0.0;

        foreach ($runningPositions as $position) {
            // On vérifie que la quantité et le prix d'achat LVC sont renseignés
            if ($position->getQuantity() && $position->getLvcBuyPrice()) {
                $totalEngaged += ($position->getQuantity() * (float) $position->getLvcBuyPrice());
            }
        }

        return $totalEngaged;
    }

    /**
     * Calcule le pourcentage d'exposition actuel du portefeuille.
     * Se base sur le snapshot enregistré en base et qui fait autorité.
     */
    public function getExposureData(User $user): array
    {
        // On récupère le snapshot qui fait autorité (PRU, Cash, Equity)
        $snapshot = $this->calculateCurrentSnapshot($user);

        $totalCapital = $snapshot['total_equity'];
        $usedCapital = $totalCapital - $snapshot['cash_amount']; // Valeur actuelle des actifs

        $percentage = $totalCapital > 0 ? round(($usedCapital / $totalCapital) * 100) : 0;
        $remainingCapital = $snapshot['cash_amount'];

        return [
            'chart' => $this->createExposureChart($usedCapital, $remainingCapital, $percentage),
            'percentage' => $percentage,
            'used' => $usedCapital,
            'remaining' => $remainingCapital,
            'exposure_color' => $this->getExposureColor($percentage),
            'exposure_status' => $this->getExposureStatus($percentage),
        ];
    }

    public function getExposureColor(float $percentage): string
    {
        // Logique de couleur dynamique
        return match (true) {
            $percentage >= 75 => '#ff4b5c', // Rouge
            $percentage >= 50 => '#ffa502', // Orange
            $percentage >= 25 => '#198754', // Vert
            default => '#36a2eb',           // Bleu
        };
    }

    public function getExposureStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 75 => 'PHASE 4',
            $percentage >= 50 => 'PHASE 3',
            $percentage >= 25 => 'PHASE 2',
            default => 'PHASE 1',
        };
    }

    public function getExposureLabel(float $percentage): string
    {
        return match (true) {
            $percentage >= 75 => 'ALLÈGEMENT DE L\'EXPOSITION (PHASE 4)',
            $percentage >= 50 => 'RÉCUPÉRATION DU CAPITAL INVESTI (PHASE 3)',
            $percentage >= 25 => 'ACCUMULATION DES POSITIONS (PHASE 2)',
            default => 'INVESTISSEMENT EN COURS (PHASE 1)',
        };
    }

    public function getExposureDescription(float $percentage): string
    {
        return match (true) {
            $percentage >= 75 => 'Revente complète de la ligne. Si la revente ne permet pas de retomber sous 75 % d\'exposition, on complète avec des positions CORE.',
            $percentage >= 50 => 'Revente totale de la ligne. Récupération du capital et de la plus-value.',
            $percentage >= 25 => 'Revente des positions limitée au seul capital investi. La plus-value reste investie.',
            default => 'Achat de positions sans revente. Représente l\'actif CORE.',
        };
    }

    private function createExposureChart(float $used, float $remaining, float $percentage): Chart
    {
        // Récupère la couleur correspondant au pourcentage
        $color = $this->getExposureColor($percentage);

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
            'cutout' => '80%', // Un anneau plus fin fait paraître le graphique plus petit et élégant
        ]);

        return $chart;
    }

    /**
     * Regroupe les positions pour un affichage par ligne, présentant la quantité et le PRU de chaque position.
     */
    public function getGroupedPositions(User $user): array
    {
        // Récupère toutes les positions en cours de l'utilisateur (Core + Trading).
        $positions = $this->positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user);
        $grouped = [];
        $name = 'LVC';

        foreach ($positions as $position) {
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'total_quantity' => 0,
                    'total_cost' => 0,
                    'current_price' => $position->getCurrentPrice(),
                    'total_current_value' => 0,
                ];
            }

            $quantity = (float) $position->getQuantity();
            $buyPrice = (float) $position->getLvcBuyPrice();

            $grouped[$name]['total_quantity'] += $quantity;
            $grouped[$name]['total_cost'] += ($quantity * $buyPrice);
            $grouped[$name]['total_current_value'] += ($quantity * $position->getCurrentPrice());
        }

        // Calcul final du PRU et de la performance pour chaque groupe
        foreach ($grouped as &$data) {
            $data['pru'] = $data['total_cost'] / $data['total_quantity'];
            $data['pnl_euro'] = $data['total_current_value'] - $data['total_cost'];
            $data['pnl_percent'] = ($data['pnl_euro'] / $data['total_cost']) * 100;
        }

        // SÉCURITÉ : on détruit la variable "pointeur" $data pour casser le lien de référence (&).
        unset($data);

        return $grouped;
    }

    /**
     * Calcule les statistiques de base pour le core.
     */
    public function getCoreStats(User $user): array
    {
        // 1. Récupérer toutes les positions marquées 'isCore'
        $corePositions = $this->positionRepository->findByStatusUserAndCore(
            PositionStatus::RUNNING,
            $user,
            true
        );

        $totalValue = 0.0;
        $totalCost = 0.0;
        $totalQuantity = 0;

        foreach ($corePositions as $pos) {
            $quantity = (float) $pos->getQuantity();
            $totalQuantity += $quantity;
            $totalCost += ($quantity * (float) $pos->getLvcBuyPrice());
            $totalValue += ($quantity * $pos->getCurrentPrice());
        }

        // 2. Calcul de la performance latente
        $performanceValue = $totalValue - $totalCost;
        $performancePercent = $totalCost > 0 ? ($performanceValue / $totalCost) * 100 : 0;

        // 3. Calculer l'objectif dynamique (25% de l'Equity totale)
        $currentSnapshot = $this->calculateCurrentSnapshot($user);
        $totalEquity = $currentSnapshot['total_equity'];
        $targetValue = $totalEquity * 0.25;

        return [
            'current_value' => $totalValue,
            'target_value' => $targetValue,
            'pru' => $totalQuantity > 0 ? ($totalCost / $totalQuantity) : 0,
            'performance_percent' => $performancePercent,
            'total_quantity' => $totalQuantity,
            'progress_percent' => $targetValue > 0 ? min(100, ($totalValue / $targetValue) * 100) : 0,
        ];
    }

    /**
     * Récupère les données de performance pour le graphique.
     */
    public function getPerformanceData(User $user): array
    {
        // 1. Données actuelles (Live)
        $currentStats = $this->calculateCurrentSnapshot($user);

        // 2. Calcul de la performance quotidienne. On cherche le dernier snapshot enregistré (celui de la veille).
        $lastSnapshot = $this->snapshotRepo->findOneBy(['owner' => $user], ['createdAt' => 'DESC'],
        );

        $dailyDiff = 0;
        $dailyPercent = 0;

        if ($lastSnapshot) {
            $dailyDiff = $currentStats['total_equity'] - $lastSnapshot->getTotalEquity();
            $dailyPercent = ($dailyDiff / $lastSnapshot->getTotalEquity()) * 100;
        }

        // Extraction des données pour Chart.js
        $labels = [];
        $data = [];

        // 3. Récupération des données pour le GRAPHIQUE (30 derniers jours).
        $history = $this->snapshotRepo->findBy(['owner' => $user], ['createdAt' => 'ASC'], 30);

        // 4. Récupération du premier snapshot de l'utilisateur
        $firstSnapshot = $this->snapshotRepo->findOneBy(['owner' => $user], ['createdAt' => 'ASC']);

        $globalPerf = [
            'is_calculable' => false,
            'diff' => 0,
            'percent' => 0,
        ];

        if ($firstSnapshot) {
            $diff = $currentStats['total_equity'] - $firstSnapshot->getTotalEquity();
            $globalPerf = [
                'is_calculable' => true,
                'diff' => $diff,
                // On calcule par rapport à la valeur de départ
                'percent' => ($firstSnapshot->getTotalEquity() > 0)
                    ? ($diff / $firstSnapshot->getTotalEquity()) * 100
                    : 0,
            ];
        }

        foreach ($history as $snapshot) {
            $labels[] = $snapshot->getCreatedAt()?->format('d/m');
            $data[] = $snapshot->getTotalEquity();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'performance_data' => $globalPerf,
            'daily_diff' => $dailyDiff,
            'daily_percent' => $dailyPercent,
        ];
    }

    /**
     * Calcule le nombre de LVC CORE qu'il faudra vendre pour repasser sous les 75% d'exposition,
     * basé sur un prix de LVC donné (le prix de vente cible).
     */
    public function calculateCoreQuantityToReduceExposure(User $user, float $lvcPrice): int
    {
        $portfolioData = $this->calculateCurrentSnapshot($user);
        $totalPortfolioValue = $portfolioData['total_value'];
        $currentExposureValue = $portfolioData['exposure_value'];

        // Calculer le montant en € à revendre pour atteindre 74.9%
        $targetExposureValue = $totalPortfolioValue * 0.749;
        $amountToLiquidate = $currentExposureValue - $targetExposureValue;

        if ($amountToLiquidate <= 0) {
            return 0;
        }

        // Convertir en nombre de parts LVC complets
        return (int) ceil($amountToLiquidate / $lvcPrice);
    }
}
