<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Strategy;

use App\Dto\MarketData\CacDailyDto;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\LogOrigin;
use App\Enum\PositionStatus;
use App\Service\LogManager;
use App\Service\PositionManager;
use App\Service\Strategy\PhaseTwoSellingStrategy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhaseTwoSellingStrategyTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PositionManager&MockObject $positionManager;
    private LogManager&MockObject $logManager;
    private PhaseTwoSellingStrategy $strategy;
    private CacDailyDto $cacDailyDto;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->positionManager = $this->createMock(PositionManager::class);
        $this->logManager = $this->createMock(LogManager::class);

        // Le DTO étant une classe finale, on instancie avec des données de test cohérentes
        $this->cacDailyDto = new CacDailyDto(
            id: 42,
            date: new \DateTimeImmutable('2024-01-01'),
            open: 7500.0,
            high: 7500.0,
            low: 7420.0,
            close: 7500.0,
            lvcHigh: 127.0,
            lvcClose: 125.0
        );

        $this->strategy = new PhaseTwoSellingStrategy(
            $this->entityManager,
            $this->positionManager,
            $this->logManager
        );
    }

    #[DataProvider('supportsDataProvider')]
    public function testSupports(float $exposure, bool $hasGain, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->strategy->supports($exposure, $hasGain));
    }

    /**
     * @return array<string, array{0: float, 1: bool, 2: bool}>
     */
    public static function supportsDataProvider(): array
    {
        return [
            'Exposition trop basse (< 25%) avec gain' => [24.9, true, false],
            'Exposition limite basse (25%) avec gain' => [25.0, true, true],
            'Exposition milieu de fourchette (35%) avec gain' => [35.0, true, true],
            'Exposition limite haute (50%) avec gain' => [50.0, true, false],
            'Exposition valide (30%) MAIS pas de gain' => [30.0, false, false],
        ];
    }

    public function testExecuteWithCoreReliquatCreation(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);
        $day = $this->cacDailyDto;

        // Configuration de la position de trading initiale
        // Quantité = 10, Prix d'achat = 100€ (Capital engagé = 1000€)
        // Prix cible LVC = 125€ → Quantité théorique à vendre = 1000 / 125 = 8 parts
        // Parts restantes pour le CORE = 10 - 8 = 2 parts (>= 1.0, donc scission)
        $position = new Position();
        $position->setQuantity(10);
        $position->setLvcBuyPrice('100.00');
        $position->setLvcTargetPrice('125.00');
        $position->setStatus(PositionStatus::RUNNING);

        $globalPru = 90.0; // PRU fiscal global
        $exposure = 30.0;  // 30% d'exposition

        // Simulation de la création de la position CORE par le PositionManager
        $mockCorePosition = new Position();
        $this->positionManager->expects($this->once())
            ->method('createCorePositionFromTrading')
            ->with($position, 2.0, 125.0)
            ->willReturn($mockCorePosition);

        // On vérifie que Doctrine prend en compte la nouvelle position CORE
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($mockCorePosition);

        // On s'assure qu'un log d'arbitrage explicite est généré
        $this->logManager->expects($this->once())
            ->method('log')
            ->with(
                $this->stringContains('[Phase 2 - Expo 30.0%] Arbitrage : Capital récupéré'),
                LogAction::SELL,
                LogOrigin::WORKFLOW,
                LogContext::RUNNING
            );

        // --- ACTION ---
        $this->strategy->execute($user, $position, $day, $globalPru, $exposure);

        // --- ASSERTIONS ---
        // La position de trading d'origine doit être mutée aux montants vendus
        $this->assertSame(8, $position->getQuantity());
        $this->assertSame(8, $position->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
    }

    public function testExecuteFallbackCloseEntireLineWhenNoReliquat(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);
        $day = $this->cacDailyDto;

        // Configuration où l'arrondi absorbe tout :
        // Quantité = 2, Prix d'achat = 100€ (Capital engagé = 200€)
        // Prix cible LVC = 105€ → Quantité théorique = 200 / 105 = 1.90 -> arrondi à 2.
        // Parts restantes = 2 - 2 = 0 (< 1.0), donc fermeture intégrale.
        $position = new Position();
        $position->setQuantity(2);
        $position->setLvcBuyPrice('100.00');
        $position->setLvcTargetPrice('105.00');
        $position->setStatus(PositionStatus::RUNNING);

        $globalPru = 95.0;
        $exposure = 40.0;

        // Le PositionManager ne doit PAS être sollicité pour créer une ligne CORE
        $this->positionManager->expects($this->never())
            ->method('createCorePositionFromTrading');

        // Doctrine ne doit rien persister de nouveau
        $this->entityManager->expects($this->never())
            ->method('persist');

        // Le log doit mentionner le fallback du reliquat inférieur à 1 LVC
        $this->logManager->expects($this->once())
            ->method('log')
            ->with(
                $this->stringContains('[Phase 2] Reliquat inférieur à 1 LVC complet. Clôture intégrale'),
                LogAction::SELL,
                LogOrigin::WORKFLOW,
                LogContext::RUNNING
            );

        // --- ACTION ---
        $this->strategy->execute($user, $position, $day, $globalPru, $exposure);

        // --- ASSERTIONS ---
        $this->assertSame(0, $position->getQuantity());
        $this->assertSame(2, $position->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
    }
}
