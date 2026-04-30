<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Position;
use App\Form\PositionType;
use Doctrine\DBAL\Exception;
use App\Enum\PositionStatus;
use Psr\Log\LoggerInterface;
use App\Service\PositionManager;
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
    public function __construct(
        private readonly LoggerInterface $tradingLogger,
        private readonly StrategyManager $strategyManager
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route('/', name: 'app_home')]
    public function index(
        CacDailyRepository $cacRepository,
        PositionRepository $positionRepository,
        EntrypointRepository $entrypointRepository,
        PositionManager    $positionManager,
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

        // TODO : est-il utile de faire cette requête, sachant que l'on a la donnée à l'index[0] de CacQuotes ?
        $latestCacDto = $cacRepository->findLast();

        // Si le dernier Cac disponible diffère de celui enregistré, on vérifie si les positions ont été touchées.
        if ($latestCacDto && $user->getLastCacUpdatedId() !== $latestCacDto->getId()) {
            try {
                $positionManager->checkAndUpdatePositions($user, $latestCacDto);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Les données de marché sont momentanément indisponibles.');
                $this->tradingLogger->error($e->getMessage(), ['exception' => $e]);
            }
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

        // Récupération des positions
        $runningPositions = $positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user);
        $waitingPositions = $positionRepository->findByStatusAndUser(PositionStatus::WAITING, $user);

        // Récupération des données d'exposition via le service
        $exposure = $portfolioService->getExposureData($user, $runningPositions);

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
            'cacQuotes' => $cacQuotes,
            'lastQuote' => $currentClose,
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
        ]);
    }
}
