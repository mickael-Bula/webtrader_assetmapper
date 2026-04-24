import {Controller} from '@hotwired/stimulus';
import {Offcanvas} from 'bootstrap';

/**
 * @property {HTMLInputElement} mainInputTarget
 * @property {HTMLInputElement} spreadInputTarget
 * @property {HTMLInputElement} mainLabelTarget
 * @property {HTMLInputElement} linkedLabelTarget
 * @property {HTMLInputElement} linkedInputTarget
 * @property {HTMLInputElement} explanationTarget
 * @property {boolean} hasMainInputTarget
 * @property {string} userSpreadValue
 */
//noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static values = { userSpread: Number };
    static targets = ["mainInput", "spreadInput", "linkedInput", "mainLabel", "linkedLabel", "explanation"];

    connect() {
        // On récupère l'instance de l'offcanvas une seule fois
        this.offcanvas = new Offcanvas(document.getElementById('editorOffcanvas'));
    }

    // Lors de l'ouverture, on récupère le contexte
    open(event) {
        // On vérifie que la cible existe avant d'assigner
        if (!this.hasMainInputTarget) {
            console.error("Le champ input est introuvable !");
            return;
        }

        const btn = event.currentTarget;
        this.activeType = btn.dataset.type; // 'upper' ou 'buy'

        // Définition dynamique des labels selon le type
        if (this.activeType === 'upper') {
            this.mainLabelTarget.textContent = "Valeur UPPER RANGE";
            this.linkedLabelTarget.textContent = "Valeur BUY LIMIT (Complément)";
        } else {
            this.mainLabelTarget.textContent = "Valeur BUY LIMIT";
            this.linkedLabelTarget.textContent = "Valeur UPPER RANGE (Complément)";
        }

        // On affiche la valeur actuelle dans l'input
        this.mainInputTarget.value = btn.dataset.value;

        // Utilisation de la valeur injectée par Twig. Stimulus crée automatiquement this.userSpreadValue
        this.spreadInputTarget.value = this.userSpreadValue;

        // Calcul initial pour le preview dans le drawer
        this.calculatePreview();

        // Calcul du facteur total (Spread * 3)
        const val = parseFloat(this.mainInputTarget.value);
        const spread = this.userSpreadValue;
        const factor = 3 * (spread / 100);
        const totalFactorPercentage = (factor * 100).toFixed(0); // Ex: 6 %

        let result;

        if (this.activeType === 'upper') {
            result = Math.round(val * (1 - factor));
        } else {
            result = Math.round(val / (1 - factor));
        }

        // Calcul de l'écart réel (en pourcentage) entre la valeur initiale et le résultat
        // Math.abs garantit un nombre positif quel que soit le sens
        const actualDiff = Math.abs(((result - val) / val) * 100);

        // Mise à jour du lien
        this.linkedInputTarget.value = result.toString();

        // Mise à jour du message avec l'écart réel calculé
        this.updateExplanation(actualDiff);

        this.offcanvas.show();
    }

    // Calcul en temps réel dans le drawer (optionnel, mais recommandé pour l'UX)
    calculatePreview() {
        // Récupération sécurisée
        const val = parseFloat(this.mainInputTarget.value);
        const spread = parseFloat(this.spreadInputTarget.value);

        // Calcul uniquement si les données sont valides
        const factor = 3 * (spread / 100);
        let result;

        if (this.activeType === 'upper') {
            result = Math.round(val * (1 - factor));
        } else {
            result = Math.round(val / (1 - factor));
        }

        // Mise à jour de l'affichage
        this.linkedInputTarget.value = result.toString();
    }

    validate() {
        const val = parseFloat(this.mainInputTarget.value);
        const calcVal = parseFloat(this.linkedInputTarget.value);

        // On met à jour selon le type actif
        if (this.activeType === 'upper') {
            this.updateCardDisplay('upper', val);
            this.updateCardDisplay('buy', calcVal);
        } else {
            this.updateCardDisplay('buy', val);
            this.updateCardDisplay('upper', calcVal);
        }

        this.offcanvas.hide();
    }

    updateCardDisplay(type, value) {
        const card = document.querySelector(`[data-card-type="${type}"]`);
        if (!card) {
            return;
        }

        const display = card.querySelector('.h6');
        if (display) {
            display.textContent = value;
        }

        const btn = card.querySelector('[data-action="click->editor#open"]');
        if (btn) {
            btn.dataset.value = value;
        }
    }

    /**
     * Met à jour le message explicatif dynamiquement
     */
    updateExplanation(actualDiff) {
        const direction = (this.activeType === 'upper')
            ? "de Upper Range vers Buy Limit"
            : "de Buy Limit vers Upper Range";

        this.explanationTarget.textContent =
            `L'écart appliqué ${direction} représente une variation de ${actualDiff.toFixed(2)} % ` +
            `pour un spread utilisateur de ${this.userSpreadValue} %.`;
    }
}
