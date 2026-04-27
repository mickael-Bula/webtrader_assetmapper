<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LogEntry;
use Doctrine\ORM\EntityManagerInterface;

readonly class LogManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function log(string $message, string $actionType): void
    {
        $log = new LogEntry();
        $log->setMessage($message);
        $log->setActionType($actionType);

        $this->em->persist($log);
        $this->em->flush();
    }
}
