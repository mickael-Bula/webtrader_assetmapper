<?php

declare(strict_types=1);

namespace App\Enum;

enum LogOrigin: string
{
    case USER = 'user';
    case WORKFLOW = 'workflow';
}
