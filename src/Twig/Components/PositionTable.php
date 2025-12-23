<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
class PositionTable
{
    public string $type; // 'running' (en cours) ou 'waiting' (en attente)

    /**
     * @var array Un tableau de vos données de trading
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
