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
use Doctrine\Common\Collections\Collection;
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
     * On fixe la buy limit par défaut à 6 % sous le nouvel upper range.
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

        // On met à jour l'upper range de l'utilisateur // TODO : formater avec deux décimales
        $user->setUpperRange((string)$newCacHigh);

        // On met à jour la buy limit de l'utilisateur en appliquant le gap stratégique (par défaut 6 %).
        $buyLimit = $this->strategyManager->calculateBuyLimit($user, $newCacHigh);

        // On récupère l'Entrypoint en WAITING (celui qui doit être remonté)
        $waitingEntrypoint = $user->getWaitingEntrypoint();

        if ($waitingEntrypoint) {
            // On assigne la buy limit comme valeur du nouvel entrypoint pour faire le suivi des positions.
            $waitingEntrypoint->setEntrypoint($buyLimit);

            // On remonte les 3 positions rattachées, qui se trouvent à -6 %, -8 % et -10 %
            $positions = $this->handleWaitingPositions($waitingEntrypoint->getPositions(), $newCacHigh, $newLvcHigh);

            // On logue la mise à jour de positions en attente.
            $this->tradingLogger->info(sprintf(
                                           "Les positions en attente de l'entrypoint %d ont été mises à jour",
                                           $positions[0]->getEntrypoint()->getId())
            );
        }
        $this->entityManager->flush();
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
                    $this->handleRankOneTrigger($user, $pos->getEntrypoint(), $pos, $day);
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
     * Les positions sont créées en tenant compte du gap stratégique.
     */
    private function handleRankOneTrigger(
        User $user,
        Entrypoint $currentEntrypoint,
        Position $currentPosition,
        CacDailyDto $day
    ): void
    {
        $newCacHigh = $day->getHigh();
        $newLvcHigh = $day->getLvcHigh();

        // On passe l'entrypoint en RUNNING.
        $currentEntrypoint->setStatus(PositionStatus::RUNNING);

        // TODO : Créer une méthode pour y placer la logique de récupération et de suppression des positions en attente.
        // On récupère les positions en attente n'appartenant pas à l'entrypoint courant.
        $positionsToRemove = $this->positionRepository->findByStatusAndUser(
            PositionStatus::WAITING,
            $user,
            $currentEntrypoint->getId()
        );

        // On supprime ces positions en attente appartenant à l'entrypoint courant.
        foreach ($positionsToRemove as $position) {
            $this->entityManager->remove($position);
        }

        $this->entityManager->flush();

        // TODO : créer une méthode pour la logique de création du nouvel entrypoint.
        // On crée un nouvel entrypoint pour l'utilisateur, avec le statut WAITING.
        $newEntrypoint = new Entrypoint();
        $newEntrypoint->setStatus(PositionStatus::WAITING);

        // On calcule le point d'entrée du nouvel entrypoint, par défaut -6 % sous la position courante.
        $nextPrice = $this->strategyManager->calculateBuyLimit($user, (float)$currentPosition->getBuyPrice());
        $newEntrypoint->setEntrypoint($nextPrice);

        // On rattache la stratégie à l'utilisateur.
        $newEntrypoint->setUser($user);

        // Le point d'entrée de l'entrypoint en RUNNING devient le nouvel upperRange.
        $user->setUpperRange($currentEntrypoint->getEntrypoint());

        // La buy limit correspond au point d'entrée du nouvel entrypoint (statut WAITING).
        $user->setBuyLimit($nextPrice);

        $this->entityManager->persist($newEntrypoint);

        // Les cibles d'achat CAC et LVC sont à 0, -2 %, -4 % du nouvel entrypoint. Les valeurs sont doublées pour le LVC.
        // Génération des nouvelles positions à partir des cibles d'achat.
        $this->createWaitingPositionsForInitialEntrypoint($newEntrypoint, $newCacHigh, $newLvcHigh);

        // Récupère les positions en attente du nouvel entrypoint.
        $positions = $this->getPositionsByEntrypointAndStatus($newEntrypoint, PositionStatus::WAITING);

        // On logue la création des positions en attente du nouvel entrypoint.
        $this->tradingLogger->info(sprintf(
                                       'Les positions en attente du nouvel entrypoint %d ont été créées',
                                       $positions[0]->getEntrypoint()?->getId())
        );

        // On enregistre les nouvelles positions.
        $this->entityManager->flush();
    }

    /**
     * Création des trois positions en attente pour un entrypoint nouvellement configuré.
     * Le gap stratégique n'est pas appliqué ici.
     */
    public function createWaitingPositionsForInitialEntrypoint(Entrypoint $entrypoint, float $cac, float $lvc): void
    {
        // Il faut créer 3 positions relativement à l'entrypoint->getEntrypoint() pour chaque rang : 1 => 0, 2 => -2 et 3 => -4.
        for ($rank = 1; $rank <= 3; $rank++) {
            $position = new Position();
            $position->setEntrypoint($entrypoint);
            $position->setRank($rank);
            $position->setStatus(PositionStatus::WAITING);

            // Calcul du palier CAC.
            $cacTarget = $this->strategyManager->calculateInitialCacTargetForPosition(
                $position,
                (float)$entrypoint->getEntrypoint()
            );
            $position->setBuyPrice((string)$cacTarget);

            // Calcule du palier LVC.
            $lvcTarget = $this->strategyManager->calculateInitialLvcTargetForPosition($position, $cac, $lvc);
            $position->setLvcBuyPrice((string)$lvcTarget);

            // Calcule des cibles de vente : CAC +10 % et LVC +20 %.
            $position->setTargetPrice((string)($cacTarget * 1.1));
            $position->setLvcTargetPrice((string)($lvcTarget * 1.2));

            // On enregistre la position dans la collection de l'entrypoint, ce qui la rend immédiatement accessible.
            $entrypoint->addPosition($position);

            $this->entityManager->persist($position);
        }
    }

    /**
     * Création des trois positions en attente pour un nouvel entrypoint.
     */
    public function createWaitingPositionsForEntrypoint(Entrypoint $entrypoint, float $cac, float $lvc): void
    {
        // Il faut créer 3 positions relativement à l'entrypoint->getEntrypoint() pour chaque rang : 1 => 0, 2 => -2 et 3 => -4.
        for ($rank = 1; $rank <= 3; $rank++) {
            $position = new Position();
            $position->setEntrypoint($entrypoint);
            $position->setRank($rank);
            $position->setStatus(PositionStatus::WAITING);

            // Calcule du palier CAC.
            $cacTarget = $this->strategyManager->calculateInitialCacTargetForPosition(
                $position,
                (float)$entrypoint->getEntrypoint()
            );
            $position->setBuyPrice((string)$cacTarget);

            // Calcule du palier LVC.
            $lvcTarget = $this->strategyManager->calculateInitialLvcTargetForPosition($position, $cac, $lvc);
            $position->setLvcBuyPrice((string)$lvcTarget);

            // Calcule des cibles de vente : CAC +10 % et LVC +20 %.
            $position->setTargetPrice((string)($cacTarget * 1.1));
            $position->setLvcTargetPrice((string)($lvcTarget * 1.2));

            // On enregistre la position dans la collection de l'entrypoint, ce qui la rend immédiatement accessible.
            $entrypoint->addPosition($position);

            $this->entityManager->persist($position);
        }
    }

    /**
     * @param Collection<int, Position> $positions
     * @return Collection<int, Position>
     *
     * Création et mise à jour des positions en attente.
     */
    public function handleWaitingPositions(Collection $positions, float $cacHigh, float $lvcHigh): Collection
    {
        foreach ($positions as $pos) {
            // 1. Calcul du palier CAC, par défaut -2 % pour chaque position, auquel on ajoute le gap stratégique (6 %)
            $cacTarget = $this->strategyManager->calculateCacTargetForPosition($pos, $cacHigh);
            $pos->setBuyPrice((string)$cacTarget);

            // 2. Calcul du palier LVC correspondant (Levier x2) : quand le CAC perd 2 %, le LVC perd 4 %
            $lvcTarget = $this->strategyManager->calculateLvcTargetForPosition($pos, $lvcHigh);
            $pos->setLvcBuyPrice((string)$lvcTarget);

            // 3. Mise à jour des cibles de vente : CAC +10 % et LVC +20 %
            $pos->setTargetPrice((string)($cacTarget * 1.1));
            $pos->setLvcTargetPrice((string)($lvcTarget * 1.2));
        }

        return $positions;
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
            return 'Aucune position à supprimer.';
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

    /**
     * Retourne les positions d'un entrypoint, filtrées par leur statut.
     *
     * @param Entrypoint $entrypoint
     * @param PositionStatus $status
     * @return array<int, Position>
     */
    public function getPositionsByEntrypointAndStatus(Entrypoint $entrypoint, PositionStatus $status): array
    {
        $positions = [];
        foreach ($entrypoint->getPositions() as $position) {
            if ($position->getStatus() === $status) {
                $positions[] = $position;
            }
        }

        return $positions;
    }
}
