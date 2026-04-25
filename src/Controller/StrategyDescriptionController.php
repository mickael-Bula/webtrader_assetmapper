<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MarketData\LvcDailyRepository;
use App\Repository\MarketData\CacDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class StrategyDescriptionController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/strategy/description', name: 'app_strategy_description')]
    public function index(CacDailyRepository $cacRepo, LvcDailyRepository $lvcRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $positionSize = $user->getPositionSize();

        $lastCacPrice = $cacRepo->findLast()?->getClose();

        $lastLvcPrice = $lvcRepo->findLast()?->getClose();

        return $this->render('strategy_description/index.html.twig', [
            'lastCacPrice' => $lastCacPrice,
            'lastLvcPrice' => $lastLvcPrice,
            'positionSize' => $positionSize,
        ]);
    }
}
