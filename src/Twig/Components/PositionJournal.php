<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\LogEntry;
use App\Repository\LogEntryRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
class PositionJournal
{
    /**
     * @var array<int, LogEntry>
     */
    public array $logs = [];

    public function __construct(private readonly LogEntryRepository $logRepository)
    {
    }

    /**
     * Cette méthode est appelée automatiquement lors du rendu du composant.
     */
    public function mount(): void
    {
        $this->logs = $this->logRepository->findBy([], ['createdAt' => 'DESC'], 10);
    }
}
