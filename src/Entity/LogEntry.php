<?php

namespace App\Entity;

use App\Enum\LogOrigin;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\LogEntryRepository;

#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
class LogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $actionType = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 20, enumType: LogOrigin::class)]
    private LogOrigin $origin = LogOrigin::WORKFLOW;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contextLabel = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(?string $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getOrigin(): LogOrigin
    {
        return $this->origin;
    }

    public function setOrigin(LogOrigin $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getContextLabel(): ?string
    {
        return $this->contextLabel;
    }

    public function setContextLabel(?string $contextLabel): self
    {
        $this->contextLabel = $contextLabel;
        return $this;
    }
}
