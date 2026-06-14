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
use App\Repository\PositionRepository;
use App\Service\LogManager;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Service\Strategy\PhaseFourSellingStrategy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhaseFourSellingStrategyTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PositionRepository&MockObject $positionRepository;
    private LogManager&MockObject $logManager;
    private PhaseFourSellingStrategy $strategy;
    private CacDailyDto $cacDailyDto;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->logManager = $this->createMock(LogManager::class);

        $this->strategy = new PhaseFourSellingStrategy(
            $this->entityManager,
            $this->positionRepository,
            $this->logManager
        );

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
            'Expo juste sous la limite (74.9%) avec gain' => [74.9, true, false],
            'Expo limite basse (75.0%) avec gain' => [75.0, true, true],
            'Expo très haute (85.0%) avec gain' => [85.0, true, true],
            'Expo valide (80.0%) MAIS pas de gain' => [80.0, false, false],
        ];
    }

    public function testExecuteTradingLineOnlyNoCoreRequired(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);
        $day = $this->cacDailyDto;

        // Position de trading : Qté initiale = 10
        $position = new Position();
        $position->setQuantity(10);
        $position->setLvcTargetPrice('120.00');
        $position->setStatus(PositionStatus::RUNNING);

        // Pas de besoin CORE : la quantité ajustée est égale à la quantité de trading
        $position->setAdjustedTargetSellQuantity(10);

        $globalPru = 100.0;
        $exposure = 76.0;

        // Le repository ne doit pas chercher de lignes CORE
        $this->positionRepository->expects($this->never())->method('findByStatusUserAndCore');
        $this->entityManager->expects($this->never())->method('persist');

        // Log attendu sans mention de revente CORE
        $this->logManager->expects($this->once())
            ->method('log')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('(Qte: 10) pour réduction des risques'),
                    $this->stringContains('Plus-value fiscale totale : 200 €.') // (120 - 100) * 10
                ),
                LogAction::SELL,
                LogOrigin::WORKFLOW,
                LogContext::RUNNING
            );

        // --- ACTION ---
        $this->strategy->execute($user, $position, $day, $globalPru, $exposure);

        // --- ASSERTIONS ---
        $this->assertSame(0, $position->getQuantity());
        $this->assertSame(10, $position->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());
    }

    public function testExecuteWithForcedCoreLiquidationLifo(): void
    {
        // --- ARRANGEMENT ---
        $user = $this->createMock(User::class);
        $day = $this->cacDailyDto;

        // 1. Ligne de trading à liquider d'office (Qté = 10)
        $position = new Position();
        $position->setQuantity(10);
        $position->setLvcTargetPrice('120.00');
        $position->setStatus(PositionStatus::RUNNING);

        // Quantité globale à vendre estimée en amont = 15 parts.
        // Il faut donc aller chercher 5 parts de CORE (15 - 10).
        $position->setAdjustedTargetSellQuantity(15);

        $globalPru = 100.0;
        $exposure = 82.0;

        // 2. Préparation du scénario CORE de type LIFO
        // On simule deux lignes CORE en cours.
        // La première a 3 parts (va être entièrement vidée), la seconde a 10 parts (on va lui prendre deux parts).
        $corePos1 = new Position();
        $corePos1->setQuantity(3);
        $corePos1->setSoldQuantity(0);
        $corePos1->setStatus(PositionStatus::RUNNING);

        $corePos2 = new Position();
        $corePos2->setQuantity(10);
        $corePos2->setSoldQuantity(1);
        $corePos2->setStatus(PositionStatus::RUNNING);

        $this->positionRepository->expects($this->once())
            ->method('findByStatusUserAndCore')
            ->with(PositionStatus::RUNNING, $user, true, 'DESC')
            ->willReturn([$corePos1, $corePos2]);

        // Doctrine doit enregistrer les deux lignes CORE modifiées
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function (Position $position) use (&$calledTargets) {
                $calledTargets[] = $position;
                return true;
            }));

        // Validation du log global de désendettement
        // PNL Trading = (120 - 100) * 10 = 200€
        // PNL CORE consumé = (120 - 100) * 5 = 100€ → Total PNL attendu = 300€
        $this->logManager->expects($this->once())
            ->method('log')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('(Qte: 10) + Liquidation forcée de 5 LVC CORE pour réduction des risques'),
                    $this->stringContains('Plus-value fiscale totale : 300 €.')
                ),
                LogAction::SELL,
                LogOrigin::WORKFLOW,
                LogContext::RUNNING
            );

        // --- ACTION ---
        $this->strategy->execute($user, $position, $day, $globalPru, $exposure);

        // --- ASSERTIONS ---

        // Vérification des persists de doctrine
        $this->assertSame($corePos1, $calledTargets[0], 'Le premier persist doit être corePos1');
        $this->assertSame($corePos2, $calledTargets[1], 'Le second persist doit être corePos2');

        // Vérification de la ligne de trading
        $this->assertSame(0, $position->getQuantity());
        $this->assertSame(10, $position->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $position->getStatus());

        // Vérification de la première ligne CORE (Totalement vidée)
        $this->assertSame(0, $corePos1->getQuantity());
        $this->assertSame(3, $corePos1->getSoldQuantity());
        $this->assertSame(PositionStatus::CLOSED, $corePos1->getStatus());

        // Vérification de la deuxième ligne CORE (Partiellement entamée : 10 - 2 = 8)
        $this->assertSame(8, $corePos2->getQuantity());
        $this->assertSame(3, $corePos2->getSoldQuantity()); // 1 initial + 2 prélevés
        $this->assertSame(PositionStatus::RUNNING, $corePos2->getStatus()); // Reste active
    }
}
