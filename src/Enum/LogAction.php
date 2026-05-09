<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Utilisé pour distinguer dans les logs les actions réalisées sur les positions.
 */
enum LogAction: string {
    case PENDING_ORDER_CREATE = 'pending_order_create';
    case BUY = 'buy';
    case SELL = 'sell';
    case TRAILING_ADJUSTMENT = 'trailing_adjustment';
    case POSITION_CLEANUP = 'position_cleanup';
    case SETUP = 'setup';
}
