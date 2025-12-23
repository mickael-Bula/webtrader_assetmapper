<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Simulation de données venant d'une base de données ou d'une API
        $runningPositions = [
            [
                'date' => '28/11/24',
                'qty' => 10,
                'id' => '1234',
                'lvc_buy' => '7400.00',
                'cac_buy' => '12.00',
                'lvc_sell' => 'NON',
                'cac_sell' => 'NON'
            ],
            [
                'date' => '05/12/24',
                'qty' => 5,
                'id' => '1235',
                'lvc_buy' => '7450.00',
                'cac_buy' => '12.10',
                'lvc_sell' => 'NON',
                'cac_sell' => 'NON'
            ],
        ];

        $waitingPositions = [
            [
                'date' => '01/03/25', // Ici, c'est la "Validité".
                'qty' => 5,
                'id' => '5678',
                'lvc_buy' => '7300.00',
                'cac_buy' => '11.50',
                'lvc_sell' => 'NON',
                'cac_sell' => 'NON'
            ],
        ];

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
        ]);
    }
}
