<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Position;
use App\Entity\Entrypoint;
use App\Enum\PositionStatus;
use Psr\Log\LoggerInterface;
use App\Dto\MarketData\CacDailyDto;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EntrypointRepository;
use App\Repository\MarketData\CacDailyRepository;

readonly class PositionManager
{
    public function __construct(
        private PositionRepository     $positionRepository,
        private CacDailyRepository     $cacRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $tradingLogger,
        private StrategyManager        $strategyManager
    )
    {
    }

    /**
     * Mise à jour des positions en itérant sur chaque jour manqué de l'historique du CAC.
     */
    public function checkAndUpdatePositions(User $user, CacDailyDto $latestCacDto): void
    {
        // Récupère l'historique manqué (du plus vieux au plus récent)
        $missedCacs = $this->cacRepository->findRangeWithLvc(
            $user->getLastCacUpdatedId(),
            $latestCacDto->getId()
        );

        foreach ($missedCacs as $currentCac) {
            // On traite chaque jour de manière isolée et chronologique
            $this->processSingleDay($user, $currentCac);
        }

        // On met à jour le dernier CAC traité pour l'utilisateur.
        $user->setLastCacUpdatedId($latestCacDto->getId());
        $this->entityManager->flush();
    }

    private function processSingleDay(User $user, CacDailyDto $day): void
    {
        // 1. GESTION DE L'UPPER RANGE. Si le CAC High > Upper Range actuel, on ajuste les positions WAITING
        if ($day->getHigh() > $user->getUpperRange()) {
            $this->handleUpperRangeTrailing($user, $day);
        }

        // 2. VENTES : on vérifie si le plus haut du jour >= Target de vente de chaque position en cours.
        $runningPositions = $this->positionRepository->findByStatusAndUser(PositionStatus::RUNNING, $user);
        foreach ($runningPositions as $pos) {
            if ($day->getHigh() >= (float)$pos->getTargetPrice()) {
                $pos->setStatus(PositionStatus::CLOSED);
                $this->tradingLogger->notice("Position clôturée", ['id' => $pos->getId(), 'exit' => $day->getHigh()]);
            }
        }

        // 3. ACHATS : on vérifie si le plus bas du jour passe sous le prix d'achat de chaque position en attente.
        $this->processPurchases($user, $day);
    }

    /**
     * On assigne le plus haut du jour au nouveau upper range de l'utilisateur.
     * On fixe la buy limit 6 % sous le nouvel upper range.
     * On remonte les positions en attente de l'utilisateur vers le niveau du plus haut du CAC du jour.
     */
    private function handleUpperRangeTrailing(User $user, CacDailyDto $day): void
    {
        $newCacHigh = $day->getHigh();
        $newLvcHigh = $day->getLvcHigh();

        $this->tradingLogger->info("Trailing : Remontée de l'upper range", [
            'old' => $user->getUpperRange(),
            'new' => $newCacHigh,
        ]);

        // On met à jour l'upper range de l'utilisateur
        $user->setUpperRange((string)$newCacHigh);

        // On récupère l'Entrypoint en WAITING (celui qui doit être remonté)
        $waitingEntrypoint = $user->getWaitingEntrypoint();

        if ($waitingEntrypoint) {
            // L'ENTRYPOINT du cycle se situe 6% sous le nouveau sommet
            $newEntrypointPrice = $newCacHigh * 0.94;
            $waitingEntrypoint->setEntrypoint((string)$newEntrypointPrice);

            // La BUY LIMIT de l'utilisateur est synchronisée sur ce Rank 1
            $user->setBuyLimit((string)$newEntrypointPrice);

            // On remonte les 3 positions rattachées
            foreach ($waitingEntrypoint->getPositions() as $pos) {
                // 1. Calcul du palier CAC basé sur l'Entrypoint (Rank 1 = 0% de baisse / Entrypoint)
                $rankOffset = ($pos->getRank() - 1) * 0.02;
                $cacTarget = $newEntrypointPrice * (1 - $rankOffset);
                $pos->setBuyPrice((string)$cacTarget);

                // 2. Calcul du palier LVC correspondant (Levier x2) : quand le CAC perd 6 %, le LVC perd 12 %
                $lvcAtEntrypoint = $newLvcHigh * 0.88;
                $lvcDrop = $rankOffset * 2;
                $lvcTarget = $lvcAtEntrypoint * (1 - $lvcDrop);

                $pos->setLvcBuyPrice((string)round($lvcTarget, 2));

                // 3. Mise à jour des cibles de vente : CAC +10 % et LVC +20 %
                $pos->setTargetPrice((string)($cacTarget * 1.1));
                $pos->setLvcTargetPrice((string)($lvcTarget * 1.2));
            }
        }
    }

    /**
     * Gestion des niveaux d'achat des positions en attente.
     * Si une position de rang un est touchée, la méthode s'appelle elle-même.
     */
    private function processPurchases(User $user, CacDailyDto $day): void
    {
        $waitingPositions = $this->positionRepository->findWaitingPositionsOrderedByPrice($user);
        $rankOneTriggered = false;

        foreach ($waitingPositions as $pos) {
            if ($day->getLow() <= (float)$pos->getBuyPrice()) {
                $pos->setStatus(PositionStatus::RUNNING);
                $this->tradingLogger->info("Position ouverte", ['id' => $pos->getId(), 'entry' => $day->getLow()]);

                if ($pos->getRank() === 1) {
                    $this->handleRankOneTrigger($user, $pos->getEntrypoint(), $pos);
                    $rankOneTriggered = true;
                    // On sort de la boucle foreach car la liste des positions en attente a changé.
                    break;
                }
            }
        }

        // Si un Rank 1 a été touché, on réévalue les nouvelles positions avec le même Cac.
        if ($rankOneTriggered) {
            $this->processPurchases($user, $day);
        }
    }

    /**
     * Génération du nouvel entrypoint et des nouvelles positions en attente.
     */
    private function handleRankOneTrigger(User $user, Entrypoint $currentEntrypoint, Position $currentPosition): void
    {
        // On passe l'entrypoint en RUNNING.
        $currentEntrypoint->setStatus(PositionStatus::RUNNING);

        // On récupère les positions en attente n'appartenant pas à l'entrypoint courant.
        $positionsToRemove = $this->positionRepository->findByStatusAndUser(
            PositionStatus::WAITING,
            $user,
            $currentEntrypoint->getId()
        );

        // On supprime ces positions en attente des entrypoints précédents.
        foreach ($positionsToRemove as $position) {
            $this->entityManager->remove($position);
        }

        $this->entityManager->flush();

        // On crée un nouvel entrypoint pour l'utilisateur, avec le statut WAITING.
        $newEntrypoint = new Entrypoint();
        $newEntrypoint->setStatus(PositionStatus::WAITING);

        // On calcule le nouvel entrypoint en fonction de la position courante.
        $nextPrice = $this->strategyManager->calculateNextEntrypoint($user, $currentPosition);

        // Le nouvel entrypoint se situe par défaut 6 % sous la position courante.
        $newEntrypoint->setEntrypoint($nextPrice);

        // L'ancienne buyLimit devient le nouvel upperRange.
        $user->setUpperRange($currentPosition->getBuyPrice());

        // La nouvelle buyLimit se situe par défaut 6 % sous l'ancienne buyLimit.
        $newEntrypoint->setUser($user);

        // On calcule la buy limit pour le nouvel entrypoint, en utilisant par défaut 6 %.
        $user->setBuyLimit($this->strategyManager->calculateBuyLimit($newEntrypoint));

        $this->entityManager->persist($newEntrypoint);

        // Génération des nouvelles positions.
        $this->createWaitingPositionsForEntrypoint(
            $newEntrypoint,
            (float)$currentPosition->getBuyPrice(),
            (float)$currentPosition->getLvcBuyPrice()
        );

        // On enregistre les nouvelles positions.
        $this->entityManager->flush();
    }

    public function createWaitingPositionsForEntrypoint(Entrypoint $entrypoint, float $baseCac, float $baseLvc): void
    {
        $spread = $this->strategyManager->calculateSpread($entrypoint);

        for ($rank = 1; $rank <= 3; $rank++) {
            $position = new Position();
            $position->setEntrypoint($entrypoint);
            $position->setRank($rank);
            $position->setStatus(PositionStatus::WAITING);

            // Calcul CAC
            $cacTarget = $baseCac * (1 - (($rank - 1) * $spread));
            $position->setBuyPrice((string)$cacTarget);

            // Calcul LVC (Levier x2)
            $cacDiff = ($cacTarget / $baseCac) - 1;
            $lvcTarget = $baseLvc * (1 + ($cacDiff * 2));

            $position->setLvcBuyPrice((string)round($lvcTarget, 2));

            $this->entityManager->persist($position);
        }
    }

    /**
     * Méthode appelée uniquement lors de la configuration d'un nouvel Entrypoint par l'utilisateur.
     * Elle supprime toutes les positions en attente des entrypoints actifs de l'utilisateur
     * et passe tous les entrypoints sans positions en cours au statut CLOSED.
     */
    public function deleteFormerWaitingPositions(User $user): string
    {
        /** @var EntrypointRepository $entrypointRepo */
        $entrypointRepo = $this->entityManager->getRepository(Entrypoint::class);

        // On récupère la totalité et le dernier des entrypoints actifs de l'utilisateur
        $activeEntrypoints = $entrypointRepo->findActiveEntrypoints($user);
        $latestEntrypoint = $activeEntrypoints[0] ?? null;

        if (!$latestEntrypoint) {
            return '';
        }

        // Si au moins un ordre en cours existe pour le dernier entrypoint, toutes ses positions sont conservées.
        $startMessage = '';
        if ($latestEntrypoint->isLocked()) {
            $startMessage = 'Une ou plusieurs positions en cours existent et ont été conservées. ';
        }

        // On supprime les positions en attente de tous les entrypoints non clôturés de l'utilisateur.
        foreach ($activeEntrypoints as $entrypoint) {
            foreach ($entrypoint->getPositions() as $position) {
                if ($position->getStatus() === PositionStatus::WAITING) {
                    $entrypoint->removePosition($position);
                    $this->entityManager->remove($position);
                }
            }
            // On change le statut des entrypoints précédents et sans positions 'en cours' pour les rendre 'inactifs'.
            if (!$entrypoint->isLocked()) {
                $entrypoint->setStatus(PositionStatus::CLOSED);
                $this->entityManager->flush();
            }
        }

        return $startMessage . 'Les anciens ordres en attente ont été supprimés.';
    }
}
