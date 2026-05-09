<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Utilisé pour identifier ce qui est à l'origine de l'action réalisée sur la position.
 */
enum LogOrigin: string
{
    case USER = 'user';
    case WORKFLOW = 'workflow';
}
