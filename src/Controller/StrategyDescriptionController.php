<?php

namespace App\Controller;

use App\Repository\CacDailyRepository;
use App\Repository\LvcDailyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StrategyDescriptionController extends AbstractController
{
    #[Route('/strategy/description', name: 'app_strategy_description')]
    public function index(CacDailyRepository $cacRepo, LvcDailyRepository $lvcRepo): Response
    {
        $lastCacPrice = (float)$cacRepo->createQueryBuilder('c')
            ->select('c.close')
            ->orderBy('c.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        $lastLvcPrice = (float)$lvcRepo->createQueryBuilder('l')
            ->select('l.close')
            ->orderBy('l.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('strategy_description/index.html.twig', [
            'lastCacPrice' => $lastCacPrice,
            'lastLvcPrice' => $lastLvcPrice,
        ]);
    }
}
