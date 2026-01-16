<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Position;
use App\Entity\Entrypoint;

class StrategyManager
{
    /**
     * Calcule le prochain point d'entrée formaté en string, sans virgule.
     */
    public function calculateNextEntrypoint(User $user, Position $currentPosition): string
    {
        // 1. Récupération du prix actuel
        $buyPrice = (float) $currentPosition->getBuyPrice();

        // 2. Récupération de la config User (spread 2 par défaut entre les 3 positions), soit spread x 3 / 100 (0.06)
        $gapStrategy = $user->getSpread() * 3 / 100;

        // 3. Calcul du multiplicateur (ex: 1 - 0.06 = 0.94)
        $multiplier = 1 - $gapStrategy;
        $result = $buyPrice * $multiplier;

        // 4. Formatage strict pour la base de données (Entier)
        return number_format($result, 2, '.', '');
    }

    /**
     * Calcule la limite haute (Upper Range), basée sur le spread utilisateur.
     * Si le spread = 2 %, l'écart total pour trois positions sera de 6 %.
     */
    public function calculateUpperRange(Entrypoint $entrypoint): string
    {
        $user = $entrypoint->getUser();
        $spread = $user ? $user->getSpread() : 2;

        // Formule : Entrypoint * (1 + (Spread * 3 / 100))
        $multiplier = 1 + ($spread * 3 / 100);
        $result = (float)$entrypoint->getEntrypoint() * $multiplier;

        return number_format($result, 2, '.', '');
    }

    /**
     * @param Entrypoint $entrypoint L'entrypoint nouvellement créé.
     * Calcule la limite basse (Buy Limit)
     */
    public function calculateBuyLimit(Entrypoint $entrypoint): string
    {
        $user = $entrypoint->getUser();
        $spread = $user ? $user->getSpread() : 2;

        // Formule : Entrypoint * (1 - (Spread * 3 / 100))
        $multiplier = 1 - ($spread * 3 / 100);
        $result = round((float)$entrypoint->getEntrypoint() * $multiplier, 2);

        return number_format($result, 2, ',', '');
    }

    public function calculateSpread(Entrypoint $entrypoint): float|int
    {
        $spread = $entrypoint->getUser() ? $entrypoint->getUser()->getSpread() : 2;

        return $spread / 100;
    }
}
