<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Position;
use Symfony\Component\Form\FormView;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @noinspection PhpUnused
 */
#[AsTwigComponent]
class PositionTable
{
    public string $type; // 'running' (en cours) ou 'waiting' (en attente)
    public ?FormView $form = null; // Le formulaire de position
    public string $lastPrice;
    public string $lastLvcPrice;

    /**
     * @var array<Position> Un tableau des données de trading
     */
    public array $positions = [];

    /**
     * Gère dynamiquement le titre de la première colonne.
     *
     * @noinspection PhpUnused
     */
    public function getDateLabel(): string
    {
        return 'running' === $this->type ? 'Acheté' : 'Validité';
    }

    /**
     * Affiche la date d'achat pour les positions en cours
     * et la date d'expiration pour les positions en attente.
     *
     * @noinspection PhpUnused
     */
    public function getFormattedDate(Position $position): string
    {
        if ('running' === $this->type) {
            return $position->getCreatedAt()->format('d/m/y');
        }

        // Pour le mode waiting, on e&cupère la date d'expiration
        $expiresAt = $position->getExpiresAt();

        // Pour les anciennes lignes n'ayant pas encore de valeur, on fixe une valeur par défaut à +3 mois.
        if (null === $expiresAt) {
            $expiresAt = $position->getCreatedAt()->modify('+3 months');
        }

        return $expiresAt->format('d/m/y');
    }

    /**
     * Retourne un booléen indiquant que la date de validité est inférieure à une semaine.
     *
     * @noinspection PhpUnused
     */
    public function isExpiringSoon(Position $position): bool
    {
        if ('waiting' !== $this->type) {
            return false;
        }

        // ON récupère la date d'expiration
        $expiresAt = $position->getExpiresAt();

        // Pour les anciennes lignes n'ayant pas encore de valeur, on fixe une valeur par défaut à +3 mois.
        if (null === $expiresAt) {
            $expiresAt = $position->getCreatedAt()->modify('+3 months');
        }

        $now = new \DateTimeImmutable('today'); // 'today' pour ignorer l'influence des heures/minutes

        // On calcule la différence de jours
        $interval = $now->diff($expiresAt);
        $daysRemaining = (int) $interval->format('%r%a');

        // On considère "urgent" s'il reste entre 0 et 7 jours avant expiration
        return $daysRemaining <= 7 && $daysRemaining >= 0;
    }

    /**
     * Affiche le nombre de positions dans la table.
     */
    public function getCount(): int
    {
        return count($this->positions);
    }

    /**
     * Gère le titre de la table.
     *
     * @noinspection PhpUnused
     */
    public function getDisplayTitle(): string
    {
        $count = $this->getCount();

        // On adapte le mot "position".
        $word = $count > 1 ? 'positions' : 'position';

        // On ajoute le reste du titre.
        $suffix = ('running' === $this->type) ? 'en cours' : 'en attente';

        if (0 === $count) {
            return 'Aucune position ' . $suffix;
        }

        return sprintf('%d %s %s', $count, $word, $suffix);
    }

    /**
     * Calcule la classe CSS de l'en-tête selon le type.
     *
     * @noinspection PhpUnused
     */
    public function getHeaderClass(): string
    {
        return 'running' === $this->type ? 'position-header-running' : 'position-header-waiting';
    }
}
