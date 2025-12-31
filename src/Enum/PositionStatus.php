<?php

declare(strict_types=1);


namespace App\Enum;

enum PositionStatus: string
{
    case WAITING = 'waiting';
    case RUNNING = 'running';
    case CLOSED = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::WAITING => 'En attente',
            self::RUNNING => 'En cours',
            self::CLOSED => 'Clôturée',
        };
    }
}
