<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StrategyDescriptionController extends AbstractController
{
    #[Route('/strategy/description', name: 'app_strategy_description')]
    public function index(): Response
    {
        return $this->render('strategy_description/index.html.twig', [
            'controller_name' => 'StrategyDescriptionController',
        ]);
    }
}
