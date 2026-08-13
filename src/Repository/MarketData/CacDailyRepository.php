<?php

declare(strict_types=1);

namespace App\Repository\MarketData;

use App\Dto\MarketData\CacDailyDto;
use App\Dto\MarketData\CacLvcQuoteDto;
use App\Entity\CacDaily;
use App\Entity\LvcDaily;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CacDailyRepository implements CacDailyRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Récupère un DTO par son ID.
     *
     * @throws \Exception|Exception
     */
    public function findById(int $id): ?CacDailyDto
    {
        $sql = <<<SQL
            SELECT $id,
                   date,
                   open,
                   high,
                   low,
                   close
            FROM market_data.cac_daily
            WHERE id = :id
            SQL;

        $row = $this->connection->fetchAssociative($sql, ['id' => $id]);

        if (false === $row) {
            return null;
        }

        return new CacDailyDto(
            (int) $row['id'],
            new \DateTimeImmutable($row['date']),
            (float) $row['open'],
            (float) $row['high'],
            (float) $row['low'],
            (float) $row['close'],
        );
    }

    /**
     * Dernière cotation CAC sous forme de DTO pour affichage et calculs.
     *
     * @throws \Exception|Exception
     */
    public function findLast(): ?CacDailyDto
    {
        $sql = <<<SQL
            SELECT c.id,
                   c.date,
                   c.open,
                   c.high,
                   c.low,
                   c.close AS cac_close,
                   l.high AS lvc_high,
                   l.close AS lvc_close
            FROM market_data.cac_daily c
            LEFT JOIN market_data.lvc_daily l ON l.date = c.date
            ORDER BY date DESC
            LIMIT 1
            SQL;

        $row = $this->connection->fetchAssociative($sql);

        if (false === $row) {
            return null;
        }

        return new CacDailyDto(
            (int) $row['id'],
            new \DateTimeImmutable($row['date']),
            (float) $row['open'],
            (float) $row['high'],
            (float) $row['low'],
            (float) $row['cac_close'],
            (float) $row['lvc_high'],
            round((float) $row['lvc_close'], 2), // On formate le prix LVC enregistré dans `position.lvc_buy_price`
        );
    }

    /**
     * Dernières cotations CAC avec le prix LVC associé.
     *
     * @return array<CacLvcQuoteDto>
     *
     * @throws \Exception|Exception
     */
    public function findLastQuotesWithLvc(int $limit = 15): array
    {
        $sql = <<<SQL
            SELECT
                c.id,
                c.date,
                c.close AS cac_close,
                c.open,
                c.high,
                c.low,
                l.close AS lvc_close
            FROM market_data.cac_daily c
            LEFT JOIN market_data.lvc_daily l ON l.date = c.date
            ORDER BY c.date DESC
            LIMIT :limit
            SQL;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = new CacLvcQuoteDto(
                (int) $row['id'],
                new \DateTimeImmutable($row['date']),
                (float) $row['cac_close'],
                (float) $row['open'],
                (float) $row['high'],
                (float) $row['low'],
                isset($row['lvc_close']) ? (float) $row['lvc_close'] : null
            );
        }

        return $result;
    }

    /**
     * Récupère les cotations CAC devant être traitées, en y ajoutant le plus haut du LVC correspondant.
     *
     * @return CacDailyDto[]
     */
    public function findRangeWithLvc(int $startId, int $endId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('new App\Dto\MarketData\CacDailyDto(c.id, c.date, c.open, c.high, c.low, c.close, l.high, l.close)')
            ->from(CacDaily::class, 'c')
            ->join(LvcDaily::class, 'l', 'WITH', 'l.date = c.date')
            ->where('c.id > :startId')
            ->andWhere('c.id <= :endId')
            ->setParameter('startId', $startId)
            ->setParameter('endId', $endId)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
