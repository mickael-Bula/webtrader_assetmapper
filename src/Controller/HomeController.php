<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Position;
use App\Form\PositionType;
use Doctrine\DBAL\Exception;
use App\Enum\PositionStatus;
use App\Service\StrategyManager;
use App\Service\PortfolioService;
use App\Repository\PositionRepository;
use App\Repository\EntrypointRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    public function __construct(private readonly StrategyManager $strategyManager) {}

    /**
     * Affiche le tableau de bord principal.
     *
     * Note : La synchronisation des positions avec le marché est vérifiée
     * automatiquement par le MarketSyncSubscriber à chaque requête.
     *
     * @see MarketSyncSubscriber::onKernelRequest()
     *
     * @throws Exception
     */
    #[Route('/', name: 'app_home')]
    public function index(
        CacDailyRepository $cacRepository,
        PositionRepository $positionRepository,
        EntrypointRepository $entrypointRepository,
        PortfolioService    $portfolioService,
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // Si l'utilisateur n'a pas configuré son capital, on le redirige vers la page de description de la stratégie.
        if ($user->getTotalPortfolio() === null) {
            return $this->redirectToRoute('app_settings');
        }

        // Récupération des données du cac et du Lvc correspondant.
        $cacQuotes = $cacRepository->findLastQuotesWithLvc();

        $currentClose = $cacQuotes[0]->getCacClose();
        $previousClose = $cacQuotes[1]->getCacClose();
        $lastLvcPrice = $cacQuotes[0]->getLvcClose();

        // Calcule la variation du CAC et ajoute un signe + ou '' en fonction de la valeur. Le moins est déjà présent.
        $variation = (($currentClose - $previousClose) / $previousClose) * 100;
        $cacSubtitle = sprintf('%s%.2f %%', ($variation > 0 ? '+' : ''), $variation);

        $cacTrend = match (true) {
            $currentClose > $previousClose => 'up',
            $currentClose < $previousClose => 'down',
            default => 'neutral',
        };

        $buyLimit = (float)$user->getBuyLimit();

        // Calcul la distance de la buy limit avec le cac actuel.
        $buyLimitSpread = $this->strategyManager->calculateBuyLimitGap($currentClose, $buyLimit);

        // Calcul la tendance de la buy limit.
        $buyLimitTrend = $currentClose > $buyLimit ? 'down' : 'up';

        // Récupération de la date de création de l'entrypoint actif
        $activeEntrypoint = $entrypointRepository->getActiveEntrypoint($user);

        // Transmission des formulaires de création et de modification de position.
        $newPosition = new Position(); // On crée une instance vierge de Position pour le formulaire de création

        // Récupération du formulaire
        $form = $this->createForm(PositionType::class, $newPosition, [
            'stimulus_controller' => 'position-calculator',
            'action' => $this->generateUrl('app_position_create'), // Centralise l'URL d'action
        ]);

        // Récupération des statistiques du Core
        $coreStats = $portfolioService->getCoreStats($user);

        // Récupération des positions de trading en cours
        $runningPositions = $positionRepository->findByStatusUserAndCore(
            PositionStatus::RUNNING,
            $user,
            false
        );

        // Récupération des positions de trading en attente
        $waitingPositions = $positionRepository->findByStatusUserAndCore(
            PositionStatus::WAITING,
            $user,
            false
        );

        // Récupération des données d'exposition via le service
        $exposure = $portfolioService->getExposureData($user);

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
            'cacQuotes' => $cacQuotes,
            'lastQuote' => $currentClose,
            'lastPrice' => $cacQuotes[0]->getCacClose(),
            'lastLvcPrice' => $lastLvcPrice,
            'upperRange' => $user->getUpperRange(),
            'buyLimit' => $user->getBuyLimit(),
            'cacTrend' => $cacTrend,
            'cacSubtitle' => $cacSubtitle,
            'entrypointCreatedAt' => $activeEntrypoint->getCreatedAt()?->format('d/m/y'),
            'buyLimitSubtitle' => $buyLimitSpread,
            'buyLimitTrend' => $buyLimitTrend,
            'userSpread' => $user->getSpread(),
            'positionSize' => $user->getPositionSize(), // TODO : à remplacer au besoin par une taille dynamique de 5% du PF
            // On passe deux vues distinctes pour chacune des instances du composant PositionTable
            'formRunning' => $form->createView(),
            'formWaiting' => $form->createView(),
            'exposureChart' => $exposure['chart'],
            'exposurePercentage' => $exposure['percentage'],
            'exposureUsed' => $exposure['used'],
            'exposureRemaining' => $exposure['remaining'],
            'exposureColor' => $exposure['exposure_color'],
            'exposureStatus' => $exposure['exposure_status'],
            'coreStats' => $coreStats,
        ]);
    }
}
