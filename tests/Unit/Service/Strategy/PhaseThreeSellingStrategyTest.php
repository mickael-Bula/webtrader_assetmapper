<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Strategy;

use App\Enum\LogOrigin;
use App\Dto\MarketData\CacDailyDto;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\PositionStatus;
use App\Service\LogManager;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Service\Strategy\PhaseThreeSellingStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhaseThreeSellingStrategyTest extends TestCase
{
    private LogManager&MockObject $logManager;
    private PhaseThreeSellingStrategy $strategy;
    private CacDailyDto $cacDailyDto;

    protected function setUp(): void
    {
        $this->logManager = $this->createMock(LogManager::class);
        $this->strategy = new PhaseThreeSellingStrategy($this->logManager);
        // Le DTO étant une classe finale, on instancie avec des données de test cohérentes
        $this->cacDailyDto = new CacDailyDto(
            id:       42,
            date:     new \DateTimeImmutable('2024-01-01'),
            open:     7500.0,
            high:     7500.0,
            low:      7420.0,
            close:    7500.0,
            lvcHigh:  127.0,
            lvcClose: 125.0
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
            'Exposition trop basse (< 50%) avec gain' => [49.9, true, false],
            'Exposition limite basse (50%) avec gain' => [50.0, true, true],
            'Exposition milieu de fourchette (60%) avec gain' => [60.0, true, true],
            'Exposition limite haute (75%) avec gain' => [75.0, true, false],
            'Exposition valide (65%) MAIS pas de gain' => [65.0, false, false],
        ];
    }

    public function testExecuteClosesEntireLineStandardly(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);

        // Instanciation directe du vrai DTO final (Bonne pratique)
        $day = $this->cacDailyDto;

        // Configuration de la position (10 LVC achetés initialement)
        $position = new Position();
        // Optionnel : si ton entité utilise un Id auto-incrémenté en BDD,
        // tu peux utiliser la réflexion ou créer un mock partiel si getId() est requis dans le log.
        // Ici, on part du principe que getId() retourne null ou une valeur par défaut en mémoire.
        $position->setQuantity(10);
        $position->setLvcTargetPrice('135.00');
        $position->setStatus(PositionStatus::RUNNING);

        $globalPru = 110.0; // PRU fiscal global
        $exposure = 60.0;  // 60% d'exposition (Phase 3)

        // Calcul attendu : (135.00 - 110.0) * 10 = 250.00 € de PNL
        $expectedPnl = 250.00;

        // Validation du log attendu
        $this->logManager->expects($this->once())
            ->method('log')
            ->with(
                $this->stringContains(sprintf("Plus-value fiscale : %s €.", round($expectedPnl, 2))),
                LogAction::SELL,
                LogOrigin::WORKFLOW,
                LogContext::RUNNING
            );

        // --- ACTION ---
        $this->strategy->execute($user, $position, $day, $globalPru, $exposure);

        // --- ASSERTIONS ---
        // Clôture standard complète de la ligne de trading
        $this->assertSame(0, $position->getQuantity());
        $this->assertSame(10, $position->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
    }
}
