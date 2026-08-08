<?php

declare(strict_types=1);

namespace App\Service\Strategy;

use App\Dto\MarketData\CacDailyDto;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\PositionStatus;
use App\Repository\PositionRepository;
use App\Service\LogManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @noinspection PhpUnused
 */
readonly class PhaseFourSellingStrategy implements SalesStrategyInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PositionRepository $positionRepository,
        private LogManager $logManager,
    ) {
    }

    /**
     * Stratégie s'appliquant au-dessus de 75 % d'exposition sur une ligne en plus-value.
     * La ligne est totalement vendue.
     * Si l'exposition ne tombe pas sous les 75 % après la revente, on complète avec des LVC CORE.
     *
     * {@inheritDoc}
     */
    public function supports(float $exposure, bool $hasGain): bool
    {
        return $exposure >= 75.0 && $hasGain;
    }

    public function execute(User $user, Position $pos, CacDailyDto $day, float $globalPru, float $exposure): void
    {
        $lvcSellPrice = (float) $pos->getLvcTargetPrice();

        // 1. Clôture stricte de la ligne de TRADING courante
        $qtyTradingSold = $pos->getQuantity();
        $pos->setStatus(PositionStatus::CLOSED);
        $pos->setSoldQuantity($qtyTradingSold);
        $pos->setQuantity(0);

        // Plus-value unique pour cette ligne de trading
        $tradingPnl = ($lvcSellPrice - $globalPru) * $qtyTradingSold;
        $totalPnl = $tradingPnl;

        // 2. Gestion du désendettement complémentaire (CORE), récupérée de la propriété calculée dynamiquement.
        $totalAdjustedQty = $pos->getAdjustedTargetSellQuantity();

        // Sécurité : le total ajusté ne doit jamais être inférieur à la ligne de trading elle-même
        $qtyCoreToSell = max(0, $totalAdjustedQty - $qtyTradingSold);
        $coreLogPart = '';

        if ($qtyCoreToSell > 0) {
            // On récupère les lignes CORE actives de l'investisseur (triées en LIFO pour l'efficacité.)
            $corePositions = $this->positionRepository->findByStatusUserAndCore(
                PositionStatus::RUNNING,
                $user,
                true,
                'DESC'
            );

            $remainingCoreToSell = $qtyCoreToSell;
            $actualCoreSold = 0;

            foreach ($corePositions as $corePos) {
                if ($remainingCoreToSell <= 0) {
                    break;
                }

                $availableQty = $corePos->getQuantity();
                $take = min($availableQty, $remainingCoreToSell);

                // Mutation de la ligne CORE impactée
                $corePos->setQuantity($availableQty - $take);
                $corePos->setSoldQuantity($corePos->getSoldQuantity() + $take);

                if (0 === $corePos->getQuantity()) {
                    $corePos->setStatus(PositionStatus::CLOSED);
                }

                // Cumul de la PNL fiscale spécifique à cette ligne CORE
                $totalPnl += ($lvcSellPrice - $globalPru) * $take;

                $remainingCoreToSell -= $take;
                $actualCoreSold += $take;

                $this->entityManager->persist($corePos);
            }

            $coreLogPart = sprintf(' + Liquidation forcée de %d LVC CORE', $actualCoreSold);
        }

        // 3. Log combiné et dynamique
        $this->logManager->log(
            sprintf(
                '[Phase 4 - Expo %.1f%%] Liquidation complète de la ligne #%s '
                    .'(Qte: %d)%s pour réduction des risques. Plus-value fiscale totale : %s €.',
                $exposure,
                $pos->getId(),
                $qtyTradingSold,
                $coreLogPart,
                round($totalPnl, 2)
            ),
            actionType: LogAction::SELL,
            context: LogContext::RUNNING
        );
    }
}
