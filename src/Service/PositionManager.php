<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\LogAction;
use App\Entity\User;
use App\Enum\LogOrigin;
use App\Entity\Position;
use App\Enum\LogContext;
use App\Entity\Entrypoint;
use App\Enum\PositionStatus;
use Doctrine\DBAL\Exception;
use App\Dto\MarketData\CacDailyDto;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\EntrypointRepository;
use Doctrine\Common\Collections\Collection;
use App\Service\Strategy\SalesStrategyInterface;
use App\Repository\MarketData\LvcDailyRepositoryInterface;
use App\Repository\MarketData\CacDailyRepositoryInterface;

/**
 * Service central de gestion du cycle de vie des positions de trading.
 *
 * Cette classe orchestre la logique métier liée à la stratégie de "Grid Trading" :
 *
 * - Synchronisation des prix LVC avec le marché.
 * - Gestion du Trailing (remontée automatique des ordres en cas de hausse du CAC).
 * - Exécution simulée des ordres d'achat et de vente basés sur les plus hauts/bas du jour.
 * - Maintenance et nettoyage des points d'entrée (Entrypoints).
 */
readonly class PositionManager
{
    /**
     * @param iterable<SalesStrategyInterface> $strategies
     */
    public function __construct(
        private CacDailyRepositoryInterface $cacRepository,
        private LvcDailyRepositoryInterface $lvcDailyRepository,
        private EntityManagerInterface      $entityManager,
        private PositionRepository          $positionRepository,
        private StrategyManager             $strategyManager,
        private LogManager                  $logManager,
        private PortfolioService            $portfolioService,

        #[TaggedIterator('app.sales_strategy')] // Permet à Symfony de collecter toutes les classes ayant ce tag
        private iterable                    $strategies
    ) {}

    /**
     * Synchronise les prix actuels et traite les événements de trading (achats/ventes).
     *
     * Cette méthode met à jour 'lvcCurrentPrice' pour toutes les positions RUNNING,
     * puis simule chronologiquement les jours manqués si nécessaire.
     *
     * @param User $user L'investisseur concerné.
     * @param CacDailyDto $latestCacDto La dernière cotation de marché disponible.
     *
     * @throws \Exception Si une erreur survient lors du calcul ou de la persistence.
     *
     * @see MarketSyncSubscriber  Déclencheur automatique de cette méthode à chaque requête.
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

        // On flush la mise à jour de prix pour que le snapshot initial soit juste
        $this->entityManager->flush();

        // Récupère l'historique manqué (du plus vieux au plus récent)
        $missedCacs = $this->cacRepository->findRangeWithLvc(
            $user->getLastCacUpdatedId(),
            $latestCacDto->getId()
        );

        foreach ($missedCacs as $currentCac) {
            // On traite chaque jour de manière isolée et chronologique
            $this->processSingleDay($user, $currentCac);

            // On met à jour le dernier CAC traité pour l'utilisateur.
            $user->setLastCacUpdatedId($latestCacDto->getId());
            $this->entityManager->flush();
        }
    }

    /**
     * Traite l'activité boursière sur une journée précise.
     *
     * Étapes de traitement :
     * 1. Trailing : Ajuste l'Upper Range si le marché atteint de nouveaux sommets.
     * 2. Ventes : Vérifie si les cibles de vente (Targets) ont été atteintes (plus haut du jour).
     * 3. Achats : Vérifie si les prix d'entrée ont été touchés (plus bas du jour).
     *
     * @param User        $user L'utilisateur concerné.
     * @param CacDailyDto $day  Les données de cotation de la journée traitée.
     */
    private function processSingleDay(User $user, CacDailyDto $day): void
    {
        // 1. GESTION DE L'UPPER RANGE. Si le CAC High > Upper Range actuel, on ajuste les positions WAITING
        if ($day->getHigh() > $user->getUpperRange()) {
            $this->handleUpperRangeTrailing($user, $day);
        }

        // 2. GESTION DES VENTES PAR PHASES.
        $this->processDailySales($user, $day);

        // 3. ACHATS : on vérifie si le plus bas du jour passe sous le prix d'achat de chaque position en attente.
        $this->processPurchases($user, $day);
    }

    /**
     * Ajuste la stratégie suite à une hausse du marché (Trailing).
     *
     * On assigne le plus haut du jour au nouvel upper range de l'utilisateur.
     * On fixe la buy limit par défaut à 6 % sous le nouvel upper range.
     * On remonte les positions en attente de l'utilisateur vers le niveau du plus haut du CAC du jour.
     *
     * @param User        $user L'utilisateur concerné.
     * @param CacDailyDto $day  La cotation ayant déclenché le trailing.
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
    }

    /**
     * Analyse et exécute les ordres d'achat en attente (WAITING).
     *
     * Si une position de Rang 1 est exécutée, un nouveau cycle d'ordres est généré,
     * ce qui déclenche une réévaluation récursive pour le même jour de cotation.
     *
     * @param User        $user L'utilisateur concerné.
     * @param CacDailyDto $day  Cotation du jour pour tester les seuils d'achat.
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
     * Traite la vente par phase des positions :
     *
     * - Phase 1 (<25 % PF) : pas de revente.
     * - Phase 2 (<50 % PF) : revente de la position à hauteur du capital engagé, le reliquat de LVC devenant CORE.
     * - Phase 3 (<75 % PF) : revente de la position complète (cas le plus simple).
     * - Phase 4 (>75 % PF) : revente de la position, complétée par des LVC CORE, pourcramaner l'exposition du portefeuille sous 75 %.
     */
    public function processDailySales(User $user, CacDailyDto $day): void
    {
        $snapshot = $this->portfolioService->calculateCurrentSnapshot($user);
        $exposure = $snapshot['exposure_percent'];

        // Si l'exposition est sous 25 %, alors on est en Phase 1 : pas de revente.
        if ($exposure < 25.0) {
            return;
        }

        // On récupère uniquement les positions de TRADING (non CORE) qui sont en cours
        $runningPositions = $this->positionRepository->findByStatusUserAndCore(
            PositionStatus::RUNNING,
            $user,
            false,
            'DESC'  // Tri LIFO des positions (les plus récentes d'abord) pour la gestion globale du jour
        );

        foreach ($runningPositions as $pos) {
            // Vérification : est-ce que le plus haut du jour a touché l'objectif de vente ?
            if (null !== $pos->getTargetPrice() && $day->getHigh() >= (float)$pos->getTargetPrice()) {
                $this->executeStrategySell($user, $pos, $day, $exposure);
            }
        }
    }

    /**
     * Exécute la revente d'une position spécifique en appliquant les règles de phases
     * au LVC complet et selon le PRU fiscal global.
     *
     * @see processDailySales() Les stratégies ne sont exécutées que si l'exposition est > 25 %.
     *
     * @param User $user
     * @param Position $pos
     * @param CacDailyDto $day
     * @return void
     */
    private function executeStrategySell(User $user, Position $pos, CacDailyDto $day, float $exposure): void
    {
        // 1. Calcul du PRU Fiscal Global
        $globalPru = $this->calculateGlobalPru($user);

        // 2. Détermination des valeurs de vente (pas de division de titre)
        $lvcSellPrice = (float) $pos->getLvcTargetPrice();
        $capitalEngaged = $pos->getQuantity() * (float)$pos->getLvcBuyPrice();
        $totalRowValue = $pos->getQuantity() * (float)$lvcSellPrice;

        $hasGain = ($totalRowValue > $capitalEngaged && $lvcSellPrice > 0);

        // 3. Sélection et exécution de la bonne stratégie (utilisation du Pattern Strategy).
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($exposure, $hasGain)) {
                $strategy->execute($user, $pos, $day, $globalPru, $exposure);

                $this->entityManager->persist($pos);
                return; // Stratégie trouvée et appliquée, on s'arrête là
            }
        }

        // si on est à >= 25% d'expo mais qu'aucune stratégie ne se déclenche, c'est une anomalie.
        throw new \LogicException(sprintf("Aucune stratégie de revente trouvée pour l'exposition %.2f%%", $exposure));
    }

    /**
     * Calcule le Prix de Revient Unitaire (PRU) global pondéré de l'utilisateur.
     * C'est la valeur exacte calculée par le courtier.
     */
    public function calculateGlobalPru(User $user): float
    {
        $runningPositions = $this->positionRepository->findBy(['user' => $user, 'status' => PositionStatus::RUNNING]);

        $totalCost = 0.0;
        $totalQuantity = 0.0;

        foreach ($runningPositions as $runningPos) {
            $qty = (float)$runningPos->getQuantity();
            $totalCost += ($qty * (float)$runningPos->getLvcBuyPrice());
            $totalQuantity += $qty;
        }

        return $totalQuantity > 0 ? ($totalCost / $totalQuantity) : 0.0;
    }

    /**
     * Gère la transition d'un point d'entrée vers l'état actif.
     *
     * Lorsqu'un prix d'achat est touché :
     *
     * - L'Entrypoint courant passe en RUNNING.
     * - Un nouvel Entrypoint WAITING est calculé plus bas selon le gap stratégique.
     * - Les 3 positions rattachées au nouvel entrypoint sont générées.
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
     * Initialise un nouveau point d'entrée stratégique avec un statut en attente.
     *
     * @return Entrypoint Le nouveau point d'entrée persisité.
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
     * Dans ce cas particulier, le gap stratégique n'est pas appliqué.
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
     * Recalcule les seuils d'achat et de revente pour une collection de positions.
     *
     * Utilisé principalement lors du Trailing pour remonter les ordres existants.
     *
     * @return Collection<int, Position> La collection mise à jour.
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
     * Traite les données reçues du formulaire de création d'une position.
     * @param Position $position
     * @param User $user
     * @param string $statusValue
     * @param bool $isActive
     * @return Position
     * @throws Exception
     */
    public function createPositionFromForm(Position $position, User $user, string $statusValue, bool $isActive): Position
    {
        $status = PositionStatus::tryFrom($statusValue) ?? PositionStatus::WAITING;

        // On récupère la date saisie par l'utilisateur (ou celle par défaut).
        $operationDate = $position->getCreatedAt() ?? new \DateTimeImmutable();

        // Date de validité à trois mois par défaut (uniquement pour les positions en attente).
        if (($status === PositionStatus::WAITING)) {
            $position->setExpiresAt((new \DateTimeImmutable())->modify('+3 months'));
        }

        // --- GESTION DE L'ENTRYPOINT ---
        $entrypointRepo = $this->entityManager->getRepository(Entrypoint::class);

        // On cherche l'Entrypoint basé sur le buyPrice du formulaire, sinon on le crée.
        $buyPriceCac = $position->getBuyPrice();
        $entrypoint = $entrypointRepo->findOneBy(['entrypoint' => $buyPriceCac, 'user' => $user]);

        if (!$entrypoint) {
            $entrypoint = new Entrypoint();
            $entrypoint->setEntrypoint($buyPriceCac);
            $entrypoint->setUser($user);

            // On aligne la date de l'entrypoint sur la date de l'opération (date saisie dans le passé).
            $entrypoint->setCreatedAt($operationDate);

            $this->entityManager->persist($entrypoint);
        }

        // Si la case a été cochée, on neutralise les entrypoints précédents et on rend actif l'actuel.
        if ($isActive) {
            $entrypointRepo->updatePreviousEntrypoints($user);
            $entrypoint->setIsActive(true);
        }

        // On détermine si la position doit être Core ou non
        $isCore = $this->shouldNewPositionBeCore($user);
        $position->setIsCore($isCore);

        // Finalisation de la Position
        $position->setEntrypoint($entrypoint);
        $position->setStatus($status);
        $position->setRank(1);

        // Récupération de la dernière clôture du LVC
        $lastLvc = $this->lvcDailyRepository->findLastClose();
        $position->setLvcCurrentPrice($lastLvc);

        $this->entityManager->persist($position);
        $this->entityManager->flush();

        // Logging
        $meta = $this->getLogMetadata($status);
        $this->logManager->log(
            "Entrypoint #{$entrypoint->getId()} : position #{$position->getRank()} {$meta['verb']} à $buyPriceCac pts",
            actionType: $meta['action'],
            origin: LogOrigin::USER,
            context: $meta['context']
        );

        return $position;
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
     *
     * Fournit les métadonnées de journalisation (verbe, contexte, action) selon le statut.
     *
     * @return array{verb: string, context: LogContext, action: LogAction}
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

    /**
     * Détermine si une nouvelle position doit être marquée comme Core.
     * Retourne false si l'objectif des 25% de l'Equity est déjà atteint.
     */
    public function shouldNewPositionBeCore(User $user): bool
    {
        // On récupère l'Equity totale
        $snapshot = $this->portfolioService->calculateCurrentSnapshot($user);
        $totalEquity = $snapshot['total_equity'];

        if ($totalEquity <= 0) {
            return true; // Évite la division par zéro, autorisant le Core par défaut
        }

        // On calcule la valeur actuelle des positions Core RUNNING uniquement
        $corePositions = $this->positionRepository->findByStatusUserAndCore(
            PositionStatus::RUNNING,
            $user,
            true
        );

        $currentCoreValue = 0.0;
        foreach ($corePositions as $pos) {
            $currentCoreValue += ((float)$pos->getQuantity() * $pos->lvcCurrentPrice());
        }

        // Vérification de la règle métier : est-ce que le Core actuel représente déjà 25% ou plus de l'Equity totale ?
        $coreRatio = $currentCoreValue / $totalEquity;

        // Si le ratio actuel est inférieur à 25%, la nouvelle position est Core.
        return $coreRatio < 0.25;
    }

    /**
     * Fabrique une nouvelle position CORE à partir d'un reliquat de ligne de trading.
     */
    public function createCorePositionFromTrading(Position $tradingPos, int $quantity, float $currentLvcPrice): Position
    {
        $corePos = new Position();
        $corePos->setEntrypoint($tradingPos->getEntrypoint());
        $corePos->setBuyPrice($tradingPos->getBuyPrice());
        $corePos->setLvcBuyPrice($tradingPos->getLvcBuyPrice()); // Conservation du prix historique

        $corePos->setQuantity($quantity);
        $corePos->setInitialQuantity($quantity);
        $corePos->setSoldQuantity(0);

        $corePos->setIsCore(true);
        $corePos->setStatus(PositionStatus::RUNNING);
        $corePos->setCreatedAt(new \DateTimeImmutable());
        $corePos->setLvcCurrentPrice((string)$currentLvcPrice);

        return $corePos;
    }
}
