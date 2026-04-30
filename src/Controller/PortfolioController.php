<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\UX\Chartjs\Model\Chart;
use App\Repository\PortfolioSnapshotRepository;
use App\Service\PortfolioService;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortfolioController extends AbstractController
{
    #[Route('/portfolio', name: 'app_portfolio')]
    public function index(
        PortfolioService $portfolioService,
        PortfolioSnapshotRepository $snapshotRepo,
        ChartBuilderInterface $chartBuilder,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // 1. Données actuelles (Live)
        $currentStats = $portfolioService->calculateCurrentSnapshot($user);

        // 2. Calcul de la performance quotidienne. On cherche le dernier snapshot enregistré (celui de la veille).
        $lastSnapshot = $snapshotRepo->findOneBy(
            ['owner' => $user],
            ['createdAt' => 'DESC'],
            1, // On saute le snapshot d'aujourd'hui s'il vient d'être créé
            1  // On prend le deuxième plus récent
        );

        // Alternative plus robuste pour trouver le snapshot de comparaison :
        $allSnapshots = $snapshotRepo->findBy(['owner' => $user], ['createdAt' => 'DESC'], 2);
        $yesterdaySnapshot = (count($allSnapshots) > 1) ? $allSnapshots[1] : null;

        $performanceData = [
            'is_calculable' => false,
            'diff' => 0,
            'percent' => 0
        ];

        if ($yesterdaySnapshot) {
            $diff = $currentStats['total_equity'] - $yesterdaySnapshot->getTotalEquity();
            $performanceData = [
                'is_calculable' => true,
                'diff' => $diff,
                'percent' => ($diff / $yesterdaySnapshot->getTotalEquity()) * 100
            ];
        }

        $dailyDiff = 0;
        $dailyPercent = 0;

        if ($lastSnapshot) {
            $dailyDiff = $currentStats['total_equity'] - $lastSnapshot->getTotalEquity();
            $dailyPercent = ($dailyDiff / $lastSnapshot->getTotalEquity()) * 100;
        }

        // Extraction des données pour Chart.js
        $labels = [];
        $data = [];

        foreach ($allSnapshots as $snapshot) {
            $labels[] = $snapshot->getCreatedAt()?->format('d/m');
            $data[] = $snapshot->getTotalEquity();
        }

        // Création du graphique
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);

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
                                       'ticks' => ['color' => '#6c757d']
                                   ],
                                   'x' => [
                                       'grid' => ['display' => false],
                                       'ticks' => ['color' => '#6c757d']
                                   ],
                               ],
                           ]);

        return $this->render('portfolio/index.html.twig', [
            'chart' => $chart,
            'stats' => $portfolioService->calculateCurrentSnapshot($user),
            'perf' => $performanceData,
            'daily_diff' => $dailyDiff,
            'daily_percent' => $dailyPercent,
            // On récupère les 30 derniers jours pour le graphique
            'history' => $snapshotRepo->findBy(['owner' => $user], ['createdAt' => 'ASC'], 30),
        ]);
    }
}
