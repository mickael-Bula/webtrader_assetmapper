<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LogEntry;
use App\Enum\LogAction;
use App\Enum\LogContext;
use App\Enum\LogOrigin;
use Doctrine\ORM\EntityManagerInterface;

readonly class LogManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function log(
        string $message,
        LogAction $actionType,
        LogOrigin $origin = LogOrigin::WORKFLOW,
        LogContext $context = LogContext::WAITING,
    ): void {
        $log = new LogEntry();
        $log->setMessage($message);
        $log->setOrigin($origin);
        $log->setActionType($actionType->value);
        $log->setContextLabel($context->value);

        $this->em->persist($log);
        $this->em->flush();
    }
}
