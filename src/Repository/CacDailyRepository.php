<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CacDaily;
use App\Entity\LvcDaily;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<CacDaily>
 */
class CacDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CacDaily::class);
    }

    /**
     * Récupère les dernières cotations du CAC avec le prix LVC associé
     */
    public function findLastQuotesWithLvc(int $limit = 15): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.date',
                     'c.open',
                     'c.high',
                     'c.low',
                     'c.close as cac_close',
                     'l.close as lvc_close')
            ->leftJoin(LvcDaily::class, 'l', 'WITH', 'l.date = c.date')
            ->orderBy('c.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
