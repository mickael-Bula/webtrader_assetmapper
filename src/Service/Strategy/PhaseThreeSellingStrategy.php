<?php

declare(strict_types=1);

namespace App\Service\Strategy;

use App\Dto\MarketData\CacDailyDto;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\PositionStatus;
use App\Service\LogManager;

/**
 * @noinspection PhpUnused
 */
readonly class PhaseThreeSellingStrategy implements SalesStrategyInterface
{
    public function __construct(private LogManager $logManager)
    {
    }

    /**
     * Stratégie s'appliquant entre 50 % et 75 % d'exposition sur une ligne en plus-value.
     *
     * {@inheritDoc}
     */
    public function supports(float $exposure, bool $hasGain): bool
    {
        return $exposure >= 50.0 && $exposure < 75 && $hasGain;
    }

    public function execute(User $user, Position $pos, CacDailyDto $day, float $globalPru, float $exposure): void
    {
        // Calcul de la quantité de LVC (arrondie à l'entier supérieur ou inférieur au plus près)
        $lvcSellPrice = (float) $pos->getLvcTargetPrice();

        $qtySold = $pos->getQuantity();

        $pos->setStatus(PositionStatus::CLOSED);
        $pos->setSoldQuantity($qtySold);
        $pos->setQuantity(0);

        $realPnl = ($lvcSellPrice - $globalPru) * $qtySold;

        $this->logManager->log(
            sprintf(
                '[Expo %.1f%%] Cible touchée. Clôture standard de la ligne #%s. Qte: %d. '
                .'Plus-value fiscale : %s €.',
                $exposure,
                $pos->getId(),
                $qtySold,
                round($realPnl, 2)
            ),
            actionType: LogAction::SELL,
            context: LogContext::RUNNING
        );
    }
}
