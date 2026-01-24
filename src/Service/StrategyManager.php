<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Position;
use App\Entity\Entrypoint;

class StrategyManager
{
    /**
     * Calcule la limite d'achat formatée en string.
     */
    public function calculateBuyLimit(User $user, float $buyPrice): string
    {
        $result = $buyPrice * $this->getBuyLimitGapMultiplier($user);

        // Formatage avec le point comme séparateur décimal pour satisfaire le moteur de BDD.
        return number_format($result, 2, '.', '');
    }

    /**
     * Calcule la limite haute (Upper Range), basée sur le spread utilisateur.
     * Si le spread = 2 %, l'écart total pour trois positions sera de 6 %.
     */
    public function calculateUpperRange(Entrypoint $entrypoint): string
    {
        $result = (float)$entrypoint->getEntrypoint() * $this->getUpperRangeGapMultiplier($entrypoint->getUser());

        return number_format($result, 2, '.', '');
    }

    /**
     * Retourne le spread utilisateur en pourcentage.
     * Sert à calculer l'écart entre les positions.
     */
    public function calculateSpread(Entrypoint $entrypoint): float|int
    {
        $spread = $entrypoint->getUser() ? $entrypoint->getUser()->getSpread() : 2;

        return $spread / 100;
    }

    /**
     * Calcule le prix cible initial du CAC pour une position.
     * Formule : Entrypoint de l'user - (RankOffset * Entrypoint)
     */
    public function calculateInitialCacTargetForPosition(Position $position, float $entrypointValue): float
    {
        $spread = $this->calculateSpread($position->getEntrypoint());

        // Rang 1 : Entrypoint, rang 2 : Entrypoint - 2%, rang 3 : Entrypoint - 4%
        $offset = ($position->getRank() - 1) * $spread;

        return round($entrypointValue * (1 - $offset), 2);
    }

    /**
     * Calcule le prix cible initial du LVC.
     * Détermine le prix LVC théorique correspondant à l'Entrypoint CAC,
     * puis applique le spread avec un levier x2.
     */
    public function calculateInitialLvcTargetForPosition(Position $position, float $currentCac, float $currentLvc): float
    {
        $entrypointValue = (float) $position->getEntrypoint()?->getEntrypoint();

        $spread = $this->calculateSpread($position->getEntrypoint());

        // 1. Calcul du delta entre le CAC actuel et l'Entrypoint (ex. : CAC à 8000 et Entrypoint à 7600, baisse de 5%)
        $deltaCac = ($entrypointValue - $currentCac) / $currentCac;

        // 2. Application du delta au LVC avec levier x2
        $lvcAtEntrypoint = $currentLvc * (1 + ($deltaCac * 2));

        // 3. Application du spread de rang (0%, -4%, -8% sur le LVC)
        $lvcOffset = ($position->getRank() - 1) * $spread * 2;

        return round($lvcAtEntrypoint * (1 - $lvcOffset), 2);
    }

    /**
     * Retourne l'écart entre l'upper range et la buy limit en fonction du spread utilisateur.
     */
    public function getGapStrategy(User $user): float|int
    {
        return $user->getSpread() * 3 / 100;
    }

    /**
     * Retourne le multiplicateur pour calculer la limite basse (Buy Limit) en fonction du spread utilisateur.
     */
    public function getBuyLimitGapMultiplier(User $user): float|int
    {
        return 1 - $this->getGapStrategy($user);
    }

    /**
     * Retourne le multiplicateur pour calculer la limite haute (Upper Range) en fonction du spread utilisateur.
     */
    public function getUpperRangeGapMultiplier(User $user): float|int
    {
        return 1 + $this->getGapStrategy($user);
    }

    /**
     * Calcule le coefficient de déclin total à appliquer par rapport au point haut.
     * Ce coefficient combine le gap de stratégie (ex : 6 %) et le spread de rang (ex : 2 %).
     *
     * Exemples basés sur un Spread de 2% (Gap = 6 % / Spread = 2 %) :
     * Rang 1 : 0.06 + (0 * 0.02) = 0.06 → Déclin de 6%  (Multiplicateur : 0.94)
     * Rang 2 : 0.06 + (1 * 0.02) = 0.08 → Déclin de 8%  (Multiplicateur : 0.92)
     * Rang 3 : 0.06 + (2 * 0.02) = 0.10 → Déclin de 10% (Multiplicateur : 0.90)
     *
     * * @throws \LogicException Si l'utilisateur ou l'entrypoint est manquant.
     */
    private function getBaseDecline(Position $position): float
    {
        $user = $position->getEntrypoint()?->getUser();

        if (!$user) {
            throw new \LogicException(
                sprintf(
                    'Impossible de calculer le gap : la position #%s n\'est rattachée à aucun utilisateur.',
                    $position->getId()
                )
            );
        }

        $gap = $this->getGapStrategy($user);
        $rankOffset = ($position->getRank() - 1) * ($user->getSpread() / 100);

        return $gap + $rankOffset;
    }

    /**
     * Retourne la limite d'achat Cac pour une position en fonction de son rang.
     * Ex. avec un gap stratégique de 6 %  (0.02 x 3 = 0.06) :
     * – avec un spread de 2 % et un rang 1 : 1 - 0.06 = 0.94 (-6 %)
     * – avec un spread de 2 % et un rang 2 : 1 - 0.08 = 0.92 (-8 %)
     * – avec un spread de 2 % et un rang 3 : 1 - 0.10 = 0.90 (-10 %)
     */
    public function calculateCacTargetForPosition(Position $position, float $newHigh): float
    {
        return round($newHigh * (1 - $this->getBaseDecline($position)), 2);
    }

    /**
     * Retourne la limite d'achat Lvc pour une position en fonction de son rang.
     * Un levier x2 est appliqué.
     */
    public function calculateLvcTargetForPosition(Position $position, float $lvcHigh): float
    {
        return round($lvcHigh * (1 - ($this->getBaseDecline($position) * 2)), 2);
    }

    /**
     * Calcule l'écart en pourcentage entre le prix actuel et la limite d'achat.
     */
    public function calculateBuyLimitGap(float $currentCac, float $buyLimit): string
    {
        if ($currentCac === 0.0) {
            return '0,00 %';
        }

        $variation = (($buyLimit - $currentCac) / $currentCac) * 100;

        // On ajoute + ou '' (si la variation est négative, le chiffre contient déjà le signe moins).
        return sprintf('%s%.2f %%', ($variation > 0 ? '+' : ''), $variation);
    }
}
