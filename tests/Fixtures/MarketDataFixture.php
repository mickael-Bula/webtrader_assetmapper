<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class MarketDataFixture
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws Exception
     */
    public function load(): void
    {
        // 1. On s'assure de l'existence du schéma market_data
        $this->connection->executeStatement('DROP TABLE IF EXISTS market_data.cac_daily CASCADE');
        $this->connection->executeStatement('DROP TABLE IF EXISTS market_data.lvc_daily CASCADE');

        // 2. Créer la table cac_daily
        $this->connection->executeStatement('
            CREATE TABLE market_data.cac_daily (
                id SERIAL PRIMARY KEY,
                date TIMESTAMP NOT NULL UNIQUE,
                open NUMERIC(10, 2) NOT NULL,
                high NUMERIC(10, 2) NOT NULL,
                low NUMERIC(10, 2) NOT NULL,
                close NUMERIC(10, 2) NOT NULL
            )
        ');

        // 3. Créer la table lvc_daily (identique à cac_daily)
        $this->connection->executeStatement('
            CREATE TABLE market_data.lvc_daily (
                id SERIAL PRIMARY KEY,
                date TIMESTAMP NOT NULL UNIQUE,
                open NUMERIC(10, 2) NOT NULL,
                high NUMERIC(10, 2) NOT NULL,
                low NUMERIC(10, 2) NOT NULL,
                close NUMERIC(10, 2) NOT NULL
            )
        ');

        // 4. Insérer les données de test dans cac_daily
        $this->connection->insert('market_data.cac_daily', [
            'date' => '2026-08-07 00:00:00',
            'open' => 7500.50,
            'high' => 7550.00,
            'low' => 7480.20,
            'close' => 7530.10,
        ]);

        // 5. Insérer les données de test dans lvc_daily
        $this->connection->insert('market_data.lvc_daily', [
            'date' => '2026-08-07 00:00:00',
            'open' => 28.00,
            'high' => 28.50,
            'low' => 27.80,
            'close' => 28.10,
        ]);
    }
}
