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
use App\Service\PositionManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @noinspection PhpUnused
 */
readonly class PhaseTwoSellingStrategy implements SalesStrategyInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PositionManager $positionManager,
        private LogManager $logManager,
    ) {
    }

    /**
     * Stratégie s'appliquant entre 25 % et 50 % d'exposition sur une ligne en plus-value.
     *
     * {@inheritDoc}
     */
    public function supports(float $exposure, bool $hasGain): bool
    {
        return $exposure >= 25.0 && $exposure < 50.0 && $hasGain;
    }

    /**
     * Revente d'une ligne avec enregistrement d'un reliquat converti en CORE.
     *
     * {@inheritDoc}
     */
    public function execute(User $user, Position $pos, CacDailyDto $day, float $globalPru, float $exposure): void
    {
        // Calcul de la quantité de LVC (arrondie à l'entier supérieur ou inférieur au plus près)
        $lvcSellPrice = (float) $pos->getLvcTargetPrice();
        $capitalEngaged = $pos->getQuantity() * (float) $pos->getLvcBuyPrice();
        $qtyTotal = $pos->getQuantity();
        $qtyToSellTheoretical = $capitalEngaged / $lvcSellPrice;
        $qtyToSell = round($qtyToSellTheoretical); // Arrondi au LVC complet

        // Sécurité : on ne peut pas vendre plus de parts que la quantité détenue sur la ligne
        $qtyToSell = min($qtyTotal, $qtyToSell);
        $qtyRemaining = ($qtyTotal - $qtyToSell);

        if ($qtyRemaining >= 1.0) { // Un reliquat n'existe que s'il reste au moins 1 LVC entier
            // A. Mutation de la ligne de Trading originale (devient la ligne clôturée pour le fisc)
            $pos->setQuantity((int) $qtyToSell);
            $pos->setSoldQuantity((int) $qtyToSell);
            $pos->setStatus(PositionStatus::CLOSED);

            // Calcul de la PNL fiscale pour les parts vendues
            $realPnl = ($lvcSellPrice - $globalPru) * $qtyToSell;

            // B. Création de la nouvelle ligne de conservation long terme (CORE)
            $newCorePosition = $this->positionManager->createCorePositionFromTrading($pos, (int) $qtyRemaining, $lvcSellPrice);
            $this->entityManager->persist($newCorePosition); // Prise en compte de la nouvelle position par doctrine

            $this->logManager->log(
                sprintf('[Phase 2 - Expo %.1f%%] Arbitrage : Capital récupéré sur la ligne #%s '
                        .'(Vente de %d LVC, PV fiscale : %s €). Création de la ligne CORE gratuite de %d LVC.',
                    $exposure,
                    $pos->getId(),
                    $qtyToSell,
                    round($realPnl, 2),
                    $qtyRemaining
                ),
                actionType: LogAction::SELL,
                context: LogContext::RUNNING
            );
        } else {
            // Fallback si l'arrondi absorbe tout
            $this->closeEntireLine($pos, $lvcSellPrice, $globalPru);
        }
    }

    /**
     * Revente d'une ligne sans reliquat lors de la phase 2.
     * Quand l'arrondi au LVC complet absorbe la quasi-totalité de la ligne, on ferme tout.
     */
    private function closeEntireLine(Position $pos, float $lvcSellPrice, float $globalPru): void
    {
        $qtySold = $pos->getQuantity();
        $pos->setStatus(PositionStatus::CLOSED);
        $pos->setSoldQuantity($qtySold);
        $pos->setQuantity(0);

        $realPnl = ($lvcSellPrice - $globalPru) * $qtySold;

        $this->logManager->log(
            sprintf('[Phase 2] Reliquat inférieur à 1 LVC complet. Clôture intégrale de la ligne #%s. '
                    .'PV fiscale : %s €.', $pos->getId(), round($realPnl, 2)
            ),
            actionType: LogAction::SELL,
            context: LogContext::RUNNING
        );
    }
}
