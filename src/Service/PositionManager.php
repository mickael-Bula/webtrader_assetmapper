<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\LogAction;
use App\Entity\User;
use App\Entity\Position;
use App\Enum\LogContext;
use App\Entity\Entrypoint;
use App\Enum\PositionStatus;
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
        private StrategyManager        $strategyManager,
        private LogManager             $logManager,
    )
    {
    }

    /**
     * Mise à jour des positions en itérant sur chaque jour manqué de l'historique du CAC.
     */
    public function checkAndUpdatePositions(User $user, CacDailyDto $latestCacDto): void
    {
        // MISE À JOUR DU PRIX LVC COURANT DES POSITIONS EN COURS
        $runningPositions = $this->positionRepository->findByStatusAndUser(
            PositionStatus::RUNNING,
            $user
        );

        foreach ($runningPositions as $position) {
            $position->setLvcCurrentPrice((string)$latestCacDto->getLvcClose());
        }

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

        // 2. VENTES : on vérifie si le plus haut du jour >= Target de vente de chaque position en cours non CORE.
        $runningPositions = $this->positionRepository->findByStatusUserAndCore(PositionStatus::RUNNING, $user, false);
        foreach ($runningPositions as $pos) {
            if (null !== $pos->getTargetPrice() && $day->getHigh() >= (float)$pos->getTargetPrice()) {
                $pos->setStatus(PositionStatus::CLOSED);
                $this->logManager->log(
                    sprintf(
                        "Position clôturée : id #%s (Cible: %s, Haut du jour: %s)",
                        $pos->getId(),
                        $pos->getTargetPrice(),
                        $day->getHigh()
                    ),
                    actionType: LogAction::SELL,
                    context: LogContext::RUNNING,
                );
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

        $this->logManager->log(
            sprintf(
                "Trailing : Remontée de l'upper range (précédent %s, nouveau %s",
                $user->getUpperRange(),
                $newCacHigh
            ),
            actionType: LogAction::TRAILING_ADJUSTMENT
        );

        // 1. Mise à jour de l'Upper Range (formaté sur deux décimales).
        $user->setUpperRange(number_format($newCacHigh, 2, '.', ''));

        // On met à jour la buy limit de l'utilisateur en appliquant le gap stratégique (par défaut 6 %).
        $buyLimit = $this->strategyManager->calculateBuyLimit($user, $newCacHigh);
        $user->setBuyLimit($buyLimit);

        // On récupère l'Entrypoint en WAITING (celui qui doit être remonté).
        $waitingEntrypoint = $user->getWaitingEntrypoint();

        if ($waitingEntrypoint) {
            // On assigne la buy limit comme valeur du nouvel entrypoint pour faire le suivi des positions.
            $waitingEntrypoint->setEntrypoint($buyLimit);

            // On remonte les 3 positions rattachées, qui se trouvent à -6 %, -8 % et -10 %
            $positions = $this->handleWaitingPositions($waitingEntrypoint->getPositions(), $newCacHigh, $newLvcHigh);

            // On logue la mise à jour de positions en attente.
            $this->logManager->log(
                sprintf(
                    "Les positions en attente de l'entrypoint %d ont été mises à jour",
                    $positions[0]->getEntrypoint()->getId()
                ),
                  actionType: LogAction::TRAILING_ADJUSTMENT
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
                $this->logManager->log(
                    sprintf("Position ouverte : id #%s le %s", $pos->getId(), $day->getLow()),
                    actionType: LogAction::BUY
                );

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
        // 1. On passe l'entrypoint en RUNNING.
        $currentEntrypoint->setStatus(PositionStatus::RUNNING);

        // 2. Nettoyage des anciennes positions
        $this->cleanupWaitingPositions($user, $currentEntrypoint->getId());

        // 3. Création du nouvel Entrypoint
        $newEntrypoint = $this->createNewEntrypoint($user, $currentPosition);

        // 4. Mise à jour de l'utilisateur (État global)
        // Le point d'entrée de l'entrypoint en RUNNING devient le nouvel upperRange.
        $user->setUpperRange($currentEntrypoint->getEntrypoint());

        // La buy limit correspond au point d'entrée du nouvel entrypoint (statut WAITING).
        $user->setBuyLimit($newEntrypoint->getEntrypoint());

        // 5. Génération des positions liées
        // Les cibles d'achat CAC et LVC sont à 0, -2 %, -4 % du nouvel entrypoint. Les valeurs sont doublées pour le LVC.
        // Génération des nouvelles positions à partir des cibles d'achat.
        $this->createWaitingPositionsForInitialEntrypoint($newEntrypoint, $day->getHigh(), $day->getLvcHigh());

        // On enregistre le log de création des positions en attente du nouvel entrypoint.
        $this->logManager->log(
            sprintf('Les positions en attente du nouvel entrypoint %d ont été créées', $newEntrypoint->getId()),
            actionType: LogAction::PENDING_ORDER_CREATE
        );

        // On enregistre les nouvelles positions.
        $this->entityManager->flush();
    }

    /**
     * Méthode supprimant les positions en attente d'un cycle précédent.
     * On exclut les positions en attente de l'entrypoint courant.
     */
    private function cleanupWaitingPositions(User $user, int $currentEntrypointId): void
    {
        // On récupère les positions en attente n'appartenant pas à l'entrypoint courant.
        $positionsToRemove = $this->positionRepository->findByStatusAndUser(
            PositionStatus::WAITING,
            $user,
            $currentEntrypointId
        );

        // On supprime ces positions en attente appartenant à l'entrypoint courant.
        foreach ($positionsToRemove as $position) {
            $this->entityManager->remove($position);
        }
    }

    /**
     * Méthode générant un nouvel entrypoint.
     */
    private function createNewEntrypoint(User $user, Position $currentPosition): Entrypoint
    {
        // On crée un nouvel entrypoint pour l'utilisateur, avec le statut WAITING.
        $newEntrypoint = new Entrypoint();
        $newEntrypoint->setStatus(PositionStatus::WAITING);
        $newEntrypoint->setUser($user);

        // On calcule le point d'entrée du nouvel entrypoint, par défaut -6 % sous la position courante.
        $nextPrice = $this->strategyManager->calculateBuyLimit($user, (float)$currentPosition->getBuyPrice());
        $newEntrypoint->setEntrypoint($nextPrice);

        $this->entityManager->persist($newEntrypoint);

        return $newEntrypoint;
    }

    /**
     * Création des trois positions en attente pour un entrypoint nouvellement configuré.
     * Le gap stratégique n'est pas appliqué ici.
     */
    public function createWaitingPositionsForInitialEntrypoint(Entrypoint $entrypoint, float $cac, float $lvc): void
    {
        // Il faut créer trois positions relativement à l'entrypoint->getEntrypoint() pour chaque rang : 1 → 0, 2 → -2 et 3 → -4.
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

            // Enregistrement du trade en base
            $this->logManager->log(
                sprintf(
                    "Stratégie activée pour l'Entrypoint #%d : 3 positions en attente créées (Base CAC: %.2f)",
                    $entrypoint->getId(),
                    $entrypoint->getEntrypoint()
                ),
                 actionType: LogAction::PENDING_ORDER_CREATE
            );
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
        // TODO : Voir s'il est posible d'utiliser le nouveau champ isActive pour effectuer la mise à jour
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

                    // 1. Log individuel avant suppression
                    $this->logManager->log(
                        message: sprintf(
                                     "Suppression position en attente : ID #%d (Prix d'achat: %s) suite à réinitialisation",
                                     $position->getId(),
                                     $position->getBuyPrice()
                                 ),
                        actionType: LogAction::POSITION_CLEANUP
                    );

                    // 2. Suppression de la position
                    $this->entityManager->remove($position);
                }
            }
            // On change le statut des entrypoints précédents et sans positions 'en cours' pour les rendre 'inactifs'.
            if (!$entrypoint->isLocked()) {
                $entrypoint->setStatus(PositionStatus::CLOSED);

                // Log de clôture de l'entrypoint
                $this->logManager->log(
                    message: sprintf("Entrypoint #%d clôturé car sans position active", $entrypoint->getId()),
                    actionType: LogAction::POSITION_CLEANUP,
                    context: LogContext::ENTRYPOINT
                );

                $this->entityManager->flush();
            }
        }

        return $startMessage . 'Les anciens ordres en attente ont été supprimés.';
    }

    /**
     * Méthode pour adapter les messages de log en fonction du statut de la position.
     */
    public function getLogMetadata(PositionStatus $status): array
    {
        return match ($status) {
            PositionStatus::WAITING => [
                'verb'    => 'placée',
                'context' => LogContext::WAITING,
                'action'  => LogAction::SETUP,
            ],
            PositionStatus::RUNNING => [
                'verb'    => 'achetée',
                'context' => LogContext::RUNNING,
                'action'  => LogAction::BUY,
            ],
            PositionStatus::CLOSED => [
                'verb'    => 'clôturée',
                'context' => LogContext::CLOSED,
                'action'  => LogAction::SELL,
            ],
        };
    }
}
