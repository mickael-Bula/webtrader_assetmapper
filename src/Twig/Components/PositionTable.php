<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Position;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
class PositionTable
{
    public string $type; // 'running' (en cours) ou 'waiting' (en attente)

    /**
     * @var array<Position> Un tableau des données de trading
     */
    public array $positions = [];

    /**
     * Gère dynamiquement le titre de la première colonne
     * @noinspection PhpUnused
     */
    public function getDateLabel(): string
    {
        return $this->type === 'running' ? 'Acheté' : 'Validité';
    }

    /**
     * Affiche la date d'achat pour les positions en cours
     * et une date de validité calculée à trois mois pour les positions en attente.
     * @noinspection PhpUnused
     */
    public function getFormattedDate(Position $position): string
    {
        $date = clone $position->getCreatedAt();

        if ($this->type === 'waiting') {
            $date = $date->modify('+3 months');
        }

        return $date->format('d/m/y');
    }

    /**
     * Retourne un booléen indiquant que la date de validité est inférieure à une semaine ou non.
     * @noinspection PhpUnused
     */
    public function isExpiringSoon(Position $position): bool
    {
        if ($this->type !== 'waiting') {
            return false;
        }

        $validityDate = $position->getCreatedAt()->modify('+3 months');
        $now = new \DateTimeImmutable();

        // On calcule la différence
        $interval = $now->diff($validityDate);
        $daysRemaining = (int)$interval->format('%r%a');

        // On considère "urgent" s'il reste entre 0 et 7 jours
        return $daysRemaining <= 7 && $daysRemaining >= 0;
    }

    /**
     * Affiche le nombre de positions dans la table
     */
    public function getCount(): int
    {
        return count($this->positions);
    }

    /**
     * Gère le titre de la table
     * @noinspection PhpUnused
     */
    public function getDisplayTitle(): string
    {
        $count = $this->getCount();

        // On adapte le mot "position".
        $word = $count > 1 ? 'positions' : 'position';

        // On ajoute le reste du titre.
        $suffix = ($this->type === 'running') ? 'en cours' : 'en attente';

        if ($count === 0) {
            return "Aucune position " . $suffix;
        }

        return sprintf('%d %s %s', $count, $word, $suffix);
    }

    /**
     * Calcule la classe CSS de l'en-tête selon le type
     * @noinspection PhpUnused
     */
    public function getHeaderClass(): string
    {
        return $this->type === 'running' ? 'position-header-running' : 'position-header-waiting';
    }
}
