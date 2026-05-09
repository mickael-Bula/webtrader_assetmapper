<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ChartService;
use App\Repository\PortfolioSnapshotRepository;
use App\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortfolioController extends AbstractController
{
    #[Route('/portfolio', name: 'app_portfolio')]
    public function index(
        PortfolioService $portfolioService,
        PortfolioSnapshotRepository $snapshotRepo,
        ChartService $chartService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Récupération des données de performance quotidienne du portefeuille
        $performanceData = $portfolioService->getPerformanceData($user);

        // Création du graphique de performance quotidienne
        $chart = $chartService->getPerformanceChart($performanceData['labels'], $performanceData['data']);

        return $this->render('portfolio/index.html.twig', [
            'chart' => $chart,
            'stats' => $portfolioService->calculateCurrentSnapshot($user),
            'perf' => $performanceData['performance_data'],
            'daily_diff' => $performanceData['daily_diff'],
            'daily_percent' => $performanceData['daily_percent'],
            // On récupère les 30 derniers jours pour le graphique
            'history' => $snapshotRepo->findBy(['owner' => $user], ['createdAt' => 'ASC'], 30),
            'grouped_positions' => $portfolioService->getGroupedPositions($user),
        ]);
    }
}
