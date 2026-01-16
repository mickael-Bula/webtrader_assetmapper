<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use App\Enum\PositionStatus;
use Psr\Log\LoggerInterface;
use App\Service\PositionManager;
use App\Repository\PositionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $tradingLogger)
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

        return $this->render('home/index.html.twig', [
            'runningPositions' => $positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user),
            'waitingPositions' => $positionRepository->findByStatusAndUser(PositionStatus::WAITING, $user),
            'cacQuotes' => $cacRepository->findLastQuotesWithLvc(),
            'lastQuote' => $cacQuotes[0]->getcacClose(),
            'lastHigh' => $user->getUpperRange(),
            'buyLimit' => $user->getBuyLimit(),
        ]);
    }
}
