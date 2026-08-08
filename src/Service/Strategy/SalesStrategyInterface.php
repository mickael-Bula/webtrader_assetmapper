<?php

declare(strict_types=1);

namespace App\Service\Strategy;

use App\Dto\MarketData\CacDailyDto;
use App\Entity\Position;
use App\Entity\User;

interface SalesStrategyInterface
{
    /**
     * Détermine si cette stratégie doit s'appliquer en fonction du contexte.
     */
    public function supports(float $exposure, bool $hasGain): bool;

    /**
     * Exécute l'algorithme de vente propre à la phase.
     */
    public function execute(User $user, Position $pos, CacDailyDto $day, float $globalPru, float $exposure): void;
}
