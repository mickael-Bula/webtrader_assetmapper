<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use App\Repository\PositionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class HomeController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/', name: 'app_home')]
    public function index(CacDailyRepository $cacRepository, PositionRepository $positionRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $runningPositions = $positionRepository->findBy(['status' => 'running']);
        $waitingPositions = $positionRepository->findBy(['status' => 'waiting']);

        $cacQuotes = $cacRepository->findLastQuotesWithLvc();
        $lastQuote = $cacQuotes[0]->getcacClose();
        $lastHigh = $user->getUpperRange();
        $buyLimit = $user->getBuyLimit();

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
            'cacQuotes' => $cacQuotes,
            'lastQuote' => $lastQuote,
            'lastHigh' => $lastHigh,
            'buyLimit' => $buyLimit,
        ]);
    }
}
