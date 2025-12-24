<?php

namespace App\Controller;

use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * @throws RandomException
     */
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

        $cacQuotes = [];
        $startDate = new \DateTime('2024-11-29');

        // Valeur de départ arbitraire
        $currentClose = 7500.55;

        for ($i = 0; $i < 10; $i++) {
            // On simule une variation aléatoire entre -1% et +1%.
            $variation = 1 + (random_int(-100, 100) / 10000);
            $open = $currentClose;
            $close = $open * $variation;
            $high = max($open, $close) + (random_int(0, 20));
            $low = min($open, $close) - (random_int(0, 20));

            // Simulation du levier LVC (approximatif)
            $lvc = 12.50 * ($close / 7500);

            $cacQuotes[] = [
                'date' => $startDate->format('d/12/y'),
                'close' => $close,
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'lvc' => $lvc,
            ];

            // On recule d'un jour pour la ligne suivante
            $startDate->modify('-1 day');
            // La clôture du jour devient l'ouverture du lendemain
            $currentClose = $open;
        }

        return $this->render('home/index.html.twig', [
            'runningPositions' => $runningPositions,
            'waitingPositions' => $waitingPositions,
            'cacQuotes' => $cacQuotes,
        ]);
    }
}
