<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use App\Enum\PositionStatus;
use Psr\Log\LoggerInterface;
use App\Service\PositionManager;
use App\Service\StrategyManager;
use App\Repository\PositionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $tradingLogger, private readonly StrategyManager $strategyManager)
    {
    }

    /**
     * @throws Exception
     */
    #[Route('/', name: 'app_home')]
    public function index(
        CacDailyRepository $cacRepository,
        PositionRepository $positionRepository,
        PositionManager    $positionManager,
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

        // TODO : revoir le mode de calcul de lastHigh, qui correspond au dernier plus haut du CAC depuis l'enregistrement d'un entrypoint.

        return $this->render('home/index.html.twig', [
            'runningPositions' => $positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user),
            'waitingPositions' => $positionRepository->findByStatusAndUser(PositionStatus::WAITING, $user),
            'cacQuotes' => $cacQuotes,
            'lastQuote' => $currentClose,
            'lastHigh' => $user->getUpperRange(),
            'buyLimit' => $user->getBuyLimit(),
            'cacTrend' => $cacTrend,
            'cacSubtitle' => $cacSubtitle,
            'lastHighDate' => $cacQuotes[0]->getDate()->format('d/m/y'), // Exemple
            'buyLimitSubtitle' => $buyLimitSpread,
            'buyLimitTrend' => $buyLimitTrend,
        ]);
    }
}
