<?php

declare(strict_types=1);

namespace App\Repository\MarketData;

use App\Dto\MarketData\LvcDailyDto;

/**
 * Déclaration d'une interface pour permettre la création de mock PHPUnit
 * tout en conservant la déclaration 'final' pour la classe qui implémente l'interface.
 */
interface LvcDailyRepositoryInterface
{
    public function findLast(): ?LvcDailyDto;

    public function findLastClose(): string;
}
