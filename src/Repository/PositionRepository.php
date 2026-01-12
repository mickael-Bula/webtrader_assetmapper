<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Position;
use App\Enum\PositionStatus;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

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
     * @return array<Position>
     */
    public function findByStatusAndUser(PositionStatus $status, User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.entrypoint', 'e')
            ->where('e.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les positions en attente de l'utilisateur.
     * On trie par prix décroissant : on traite les targets les plus hautes d'abord
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
