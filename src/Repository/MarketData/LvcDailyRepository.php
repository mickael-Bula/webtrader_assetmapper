<?php

declare(strict_types=1);

namespace App\Repository\MarketData;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use App\Dto\MarketData\LvcDailyDto;

final readonly class LvcDailyRepository implements LvcDailyRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Dernière cotation LVC
     * @throws Exception
     * @throws \Exception
     */
    public function findLast(): ?LvcDailyDto
    {
        $sql = <<<SQL
            SELECT id, date, open, high, low, close
            FROM market_data.lvc_daily
            ORDER BY date DESC
            LIMIT 1
            SQL;

        $row = $this->connection->fetchAssociative($sql);

        if ($row === false) {
            return null;
        }

        return new LvcDailyDto(
            (int) $row['id'],
            new \DateTimeImmutable($row['date']),
            (float) $row['open'],
            (float) $row['high'],
            (float) $row['low'],
            (float) $row['close'],
        );
    }

    /**
     * Récupère le dernier cours de clôture du LVC.
     *
     * @throws Exception
     */
    public function findLastClose(): string
    {
        $result = $this->connection->fetchOne('SELECT close FROM market_data.lvc_daily ORDER BY date DESC LIMIT 1');

        if ($result === false) {
            throw new \RuntimeException("Aucune cotation LVC trouvée en base de données.");
        }

        return (string) $result; // Sécurisation du type de retour
    }
}
