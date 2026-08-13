<?php

declare(strict_types=1);

namespace App\Repository\MarketData;

use App\Dto\MarketData\CacDailyDto;
use App\Dto\MarketData\CacLvcQuoteDto;

/**
 * Déclaration d'une interface pour permettre la création de mock PHPUnit
 * tout en conservant la déclaration 'final' pour la classe qui implémente l'interface.
 */
interface CacDailyRepositoryInterface
{
    public function findById(int $id): ?CacDailyDto;

    public function findLast(): ?CacDailyDto;

    /**
     * @return array<CacLvcQuoteDto>
     */
    public function findLastQuotesWithLvc(int $limit = 15): array;

    /**
     * @return array<CacDailyDto>
     */
    public function findRangeWithLvc(int $startId, int $endId): array;
}
