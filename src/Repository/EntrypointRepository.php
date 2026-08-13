<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Entrypoint;
use App\Entity\User;
use App\Enum\PositionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entrypoint>
 */
class EntrypointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entrypoint::class);
    }

    /**
     * Retourne tous les entrypoints de l'utilisateur qui ont un statut différent de CLOSED.
     *
     * @return array<Entrypoint>
     */
    public function findActiveEntrypoints(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.user = :user')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('status', PositionStatus::CLOSED)
            ->orderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour les entrypoints précédents et retourne le nombre de lignes affectées.
     */
    public function updatePreviousEntrypoints(User $user): int
    {
        return $this->createQueryBuilder('e')
            ->update()
            ->set('e.isActive', ':status')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->setParameter('status', false)
            ->getQuery()
            ->execute();
    }

    public function getActiveEntrypoint(User $user): Entrypoint
    {
        return $this->createQueryBuilder('e')
            ->where('e.user = :user')
            ->andWhere('e.isActive = :isActive')
            ->setParameter('user', $user)
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
