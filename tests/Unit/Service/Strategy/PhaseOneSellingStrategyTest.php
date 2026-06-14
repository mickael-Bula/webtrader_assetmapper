<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Strategy;

use App\Entity\User;
use App\Service\LogManager;
use PHPUnit\Framework\TestCase;
use App\Service\PositionManager;
use App\Service\StrategyManager;
use App\Service\PortfolioService;
use App\Dto\MarketData\CacDailyDto;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MarketData\CacDailyRepositoryInterface;
use App\Repository\MarketData\LvcDailyRepositoryInterface;

class PhaseOneSellingStrategyTest extends TestCase
{
    /**
     * Test vérifiant qu'aucune stratégie n'est exécutée quand l'exposition est sous 25 %.
     */
    public function testProcessDailySalesReturnsImmediatelyInPhaseOneWithoutSideEffects(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);
        $day =  new CacDailyDto(
            id:       42,
            date:     new \DateTimeImmutable('2024-01-01'),
            open:     7500.0,
            high:     7500.0,
            low:      7420.0,
            close:    7500.0,
            lvcHigh:  127.0,
            lvcClose: 125.0
        );

        // Le PortfolioService renvoie une exposition de Phase 1 (< 25%).
        $portfolioService = $this->createMock(PortfolioService::class);
        $portfolioService->expects($this->once())
            ->method('calculateCurrentSnapshot')
            ->with($user)
            ->willReturn(['exposure_percent' => 18.0]);

        // --- ATTENTES ---
        // 1. Le repository ne doit JAMAIS être interrogé pour chercher les lignes en cours
        $positionRepository = $this->createMock(PositionRepository::class);
        $positionRepository->expects($this->never())->method('findByStatusUserAndCore');

        // 2. Le LogManager ne doit JAMAIS être appelé
        $logManager = $this->createMock(LogManager::class);
        $logManager->expects($this->never())->method('log');

        // Instanciation du manager avec les mocks
        $positionManager = new PositionManager(
            $this->createMock(CacDailyRepositoryInterface::class),
            $this->createMock(LvcDailyRepositoryInterface::class),
            $this->createMock(EntityManagerInterface::class),
            $positionRepository,
            $this->createMock(StrategyManager::class),
            $logManager,
            $portfolioService,
            [] // Pas besoin de stratégies ici
        );

        // --- ACTION ---
        $positionManager->processDailySales($user, $day);

        // --- ASSERTIONS ---
        // Le test passe si aucune attente n'a été transgressé : pas d'exception levée, pas de boucle, pas de logs.
    }
}
