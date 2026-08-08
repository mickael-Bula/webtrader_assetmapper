<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Entity\User;
use App\Enum\PositionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * Récupère les positions d'un utilisateur en passant par la table Entrypoint.
     * Cette méthode sert à construire les méthodes findByStatusAndUser et findByStatusUserAndCore.
     */
    private function getBaseQueryBuilder(User $user, PositionStatus $status): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.entrypoint', 'e')
            ->where('e.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status);
    }

    /**
     * Récupère TOUTES les positions RUNNING (Core + Trading).
     * Si un ID d'entrypoint est fourni, on exclut les positions de cet entrypoint.
     *
     * @return array<Position>
     */
    public function findByStatusAndUser(
        PositionStatus $status,
        User $user,
        ?int $excludedEntrypointId = null,
    ): array {
        $qb = $this->getBaseQueryBuilder($user, $status);

        if (null !== $excludedEntrypointId) {
            $qb->andWhere('e.id != :excludedId')
                ->setParameter('excludedId', $excludedEntrypointId);
        }

        return $qb->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère spécifiquement le bloc Core ou le bloc Trading.
     */
    public function findByStatusUserAndCore(
        PositionStatus $status,
        User $user,
        bool $isCore,
        string $order = 'ASC',
    ): array {
        return $this->getBaseQueryBuilder($user, $status)
            ->andWhere('p.isCore = :isCore')
            ->setParameter('isCore', $isCore)
            ->orderBy('p.createdAt', $order)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les positions en attente de l'utilisateur.
     * On trie par prix décroissant : on traite les targets les plus hautes d'abord.
     */
    public function findWaitingPositionsOrderedByPrice(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.entrypoint', 'e')
            ->where('e.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', PositionStatus::WAITING)
            ->orderBy('p.buyPrice', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
