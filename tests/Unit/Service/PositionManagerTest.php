<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\LogOrigin;
use App\Dto\MarketData\CacDailyDto;
use App\Entity\Entrypoint;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\PositionStatus;
use App\Repository\PositionRepository;
use App\Service\LogManager;
use App\Service\PortfolioService;
use App\Service\PositionManager;
use App\Service\StrategyManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use App\Repository\MarketData\CacDailyRepositoryInterface;
use App\Repository\MarketData\LvcDailyRepositoryInterface;

class PositionManagerTest extends TestCase
{
    private PositionRepository&MockObject $positionRepositoryMock;
    private EntityManagerInterface&MockObject $entityManagerMock;
    private PortfolioService&MockObject $portfolioServiceMock;
    private LogManager&MockObject $logManagerMock;

    private PositionManager $positionManager;
    private User $user;
    private CacDailyDto $dayDto;

    protected function setUp(): void
    {
        $this->positionRepositoryMock = $this->createMock(PositionRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->portfolioServiceMock = $this->createMock(PortfolioService::class);
        $this->logManagerMock = $this->createMock(LogManager::class);

        $this->positionManager = new PositionManager(
            $this->createMock(CacDailyRepositoryInterface::class),
            $this->createMock(LvcDailyRepositoryInterface::class),
            $this->entityManagerMock,
            $this->positionRepositoryMock,
            $this->createMock(StrategyManager::class),
            $this->logManagerMock,
            $this->portfolioServiceMock,
        );

        $this->user = new User();

        // Configuration d'un DTO de test
        $this->dayDto = new CacDailyDto(
            id: 100,
            date: new \DateTimeImmutable('2026-05-18'),
            open: 8200.0,
            high: 8300.0,  // Le plus haut du jour testé par les phases
            low: 8150.0,
            close: 8250.0,
            lvcHigh: 46.0,
            lvcClose: 45.0  // Le prix LVC de clôture utilisé pour les valorisations
        );
    }

    /**
     * Test de la PHASE 4 : L'exposition est >= 75%.
     * Attendu : Clôture intégrale immédiate de la ligne (LIFO de réduction des risques).
     */
    public function testExecuteStrategySellInPhase4LiquidatesEntirePosition(): void
    {
        // Arrange
        $position = $this->createTradingPosition(quantity: 10, buyPrice: "8000.0", lvcBuyPrice: "40.0", targetPrice: "8200.0");

        $this->positionRepositoryMock->expects($this->once())
            ->method('findByStatusUserAndCore')
            ->with(PositionStatus::RUNNING, $this->user, false, 'DESC')
            ->willReturn([$position]);

        $this->portfolioServiceMock->method('calculateCurrentSnapshot')
            ->with($this->user)
            ->willReturn(['exposure_percent' => 78.5]); // Phase 4 trigger

        // Configuration pour simuler le calcul du PRU fiscal global
        $this->positionRepositoryMock->method('findBy')
            ->with(['user' => $this->user, 'status' => PositionStatus::RUNNING])
            ->willReturn([$position]);

        // Assert
        $this->entityManagerMock->expects($this->once())->method('persist')->with($position);
        $this->logManagerMock->expects($this->once())->method('log')->with(
            $this->stringContains('[Phase 4 - Expo 78.5%]'),
            LogAction::SELL,
            LogOrigin::WORKFLOW,
            LogContext::RUNNING
        );

        // Act
        $this->positionManager->processDailySales($this->user, $this->dayDto);

        // Assertions sur l'objet muté
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
        $this->assertSame(10, $position->getSoldQuantity());
        $this->assertSame(0, $position->getQuantity());
    }

    /**
     * Test de la PHASE 3 / PHASE 1 : L'exposition est standard (ex: 60% ou 15%).
     * Attendu : Clôture intégrale classique de la position de trading ayant touché son objectif.
     */
    public function testExecuteStrategySellInPhase3ClosesEntirePosition(): void
    {
        // Arrange
        $position = $this->createTradingPosition(quantity: 5, buyPrice: "8100.0", lvcBuyPrice: "42.0", targetPrice: "8250.0");

        $this->positionRepositoryMock->method('findByStatusUserAndCore')->willReturn([$position]);
        $this->portfolioServiceMock->method('calculateCurrentSnapshot')->willReturn(['exposure_percent' => 62.0]); // Phase 3
        $this->positionRepositoryMock->method('findBy')->willReturn([$position]);

        // Assert
        $this->logManagerMock->expects($this->once())->method('log')->with(
            $this->stringContains('[Expo 62.0%] Cible touchée'),
            LogAction::SELL,
            LogOrigin::WORKFLOW,
            LogContext::RUNNING
        );

        // Act
        $this->positionManager->processDailySales($this->user, $this->dayDto);

        // Assertions
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
        $this->assertSame(5, $position->getSoldQuantity());
        $this->assertSame(0, $position->getQuantity());
    }

    /**
     * Test de la PHASE 2 : L'exposition est entre 25% et 50%, et la ligne dégage une plus-value.
     * Attendu : Mutation. Récupération du capital initial (parts vendues passées au statut CLOSED)
     * et création d'une nouvelle entité "reliquat" marquée isCore = true au statut RUNNING.
     */
    public function testExecuteStrategySellInPhase2CreatesCoreReliquat(): void
    {
        // Arrange
        // Achat initial de 10 parts à 40€ (Capital engagé = 400€).
        // Vente sur cible LVC à 50€ (Valeur totale de la ligne = 500€).
        // Parts nécessaires à vendre pour récupérer le capital : 400 / 50 = 8 parts. Reliquat = 2 parts.
        $position = $this->createTradingPosition(quantity: 10, buyPrice: "8000.0", lvcBuyPrice: "40.0", targetPrice: "8200.0");
        $position->setLvcTargetPrice("50.0");

        $this->positionRepositoryMock->method('findByStatusUserAndCore')->willReturn([$position]);
        $this->portfolioServiceMock->method('calculateCurrentSnapshot')->willReturn(['exposure_percent' => 35.0]); // Phase 2
        $this->positionRepositoryMock->method('findBy')->willReturn([$position]);

        // Assert
        // On s'attend à persister la nouvelle position CORE créée à la volée
        $this->entityManagerMock->expects($this->exactly(2))
            ->method('persist')
            ->willReturnOnConsecutiveCalls(
                [$this->isInstanceOf(Position::class)], // La nouvelle ligne CORE
                [$this->equalTo($position)]             // La ligne originale modifiée
            );

        $this->logManagerMock->expects($this->once())->method('log')->with(
            $this->stringContains('[Phase 2 - Expo 35.0%] Arbitrage : Capital récupéré'),
            LogAction::SELL,
            LogOrigin::WORKFLOW,
            LogContext::RUNNING
        );

        // Act
        $this->positionManager->processDailySales($this->user, $this->dayDto);

        // Assertions sur la ligne de Trading initiale
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
        $this->assertSame(8, $position->getSoldQuantity()); // 400€ engagés / 50€ cible = 8 parts vendues
        $this->assertSame(8, $position->getQuantity());
    }

    /**
     * Test de la PHASE 2 (Cas particulier) : Plus-value insuffisante pour isoler 1 LVC complet.
     * Attendu : Clôture intégrale classique de la ligne (pas de génération de reliquat CORE).
     */
    public function testExecuteStrategySellInPhase2ClosesEntireLineIfNoCompletableCoreRemaining(): void
    {
        // Arrange
        // Achat de 2 parts à 40€ (Capital engagé = 80€).
        // Cible touchée à 41€ (Valeur totale = 82€).
        // Calcul théorique de vente : 80 / 41 = 1.95 part, arrondie à 2.
        // Reliquat : 2 - 2 = 0. Inférieur à une part complète.
        $position = $this->createTradingPosition(quantity: 2, buyPrice: "8000.0", lvcBuyPrice: "40.0", targetPrice: "8200.0");
        $position->setLvcTargetPrice("41.0");

        $this->positionRepositoryMock->method('findByStatusUserAndCore')->willReturn([$position]);
        $this->portfolioServiceMock->method('calculateCurrentSnapshot')->willReturn(['exposure_percent' => 30.0]);
        $this->positionRepositoryMock->method('findBy')->willReturn([$position]);

        // Assert
        $this->logManagerMock->expects($this->once())->method('log')->with(
            $this->stringContains('[Phase 2] Reliquat inférieur à 1 LVC complet'),
            LogAction::SELL,
            LogOrigin::WORKFLOW,
            LogContext::RUNNING
        );

        // Act
        $this->positionManager->processDailySales($this->user, $this->dayDto);

        // Assertions
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
        $this->assertSame(2, $position->getSoldQuantity());
        $this->assertSame(0, $position->getQuantity());
    }

    /**
     * Test de la PHASE 2 (Cas de secours) : Marché baissier ou stable sans plus-value.
     * Attendu : Clôture standard via le bloc de repli.
     */
    public function testExecuteStrategySellInPhase2ClosesNormallyIfNoProfitMaturing(): void
    {
        // Arrange
        $position = $this->createTradingPosition(quantity: 10, buyPrice: "8000.0", lvcBuyPrice: "45.0", targetPrice: "8200.0");
        $position->setLvcTargetPrice("45.0"); // Aucun profit latent (Vente = Achat)

        $this->positionRepositoryMock->method('findByStatusUserAndCore')->willReturn([$position]);
        $this->portfolioServiceMock->method('calculateCurrentSnapshot')->willReturn(['exposure_percent' => 33.0]);
        $this->positionRepositoryMock->method('findBy')->willReturn([$position]);

        // Assert
        $this->logManagerMock->expects($this->once())->method('log')->with(
            $this->stringContains('[Phase 2] Pas de PV suffisante pour arbitrage'),
            LogAction::SELL,
            LogOrigin::WORKFLOW,
            LogContext::RUNNING
        );

        // Act
        $this->positionManager->processDailySales($this->user, $this->dayDto);

        // Assertions
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
        $this->assertSame(10, $position->getSoldQuantity());
        $this->assertSame(0, $position->getQuantity());
    }

    /**
     * Helper de création rapide d'une entité Position pour les tests.
     */
    private function createTradingPosition(int $quantity, string $buyPrice, string $lvcBuyPrice, string $targetPrice): Position
    {
        $entrypoint = new Entrypoint();

        $position = new Position();
        $position->setEntrypoint($entrypoint);
        $position->setStatus(PositionStatus::RUNNING);
        $position->setIsCore(false);
        $position->setQuantity($quantity);
        $position->setInitialQuantity($quantity);
        $position->setBuyPrice($buyPrice);
        $position->setLvcBuyPrice($lvcBuyPrice);
        $position->setTargetPrice($targetPrice);

        return $position;
    }
}
