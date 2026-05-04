<?php

declare(strict_types=1);

namespace App\Enum;

enum LogContext: string
{
    case WAITING = 'en attente';
    case RUNNING = 'en cours';
    case CLOSED  = 'clôturé';
    case ENTRYPOINT  = 'entrypoint';
    case PARAM = 'param';
}
