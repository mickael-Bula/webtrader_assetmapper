import {Controller} from '@hotwired/stimulus';
import {Offcanvas} from 'bootstrap';

/**
 * @property {HTMLInputElement} mainInputTarget
 * @property {HTMLInputElement} spreadInputTarget
 * @property {HTMLInputElement} mainLabelTarget
 * @property {HTMLInputElement} linkedLabelTarget
 * @property {HTMLInputElement} linkedInputTarget
 * @property {boolean} hasMainInputTarget
 * @property {string} userSpreadValue
 */
//noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static values = { userSpread: Number };
    static targets = ["mainInput", "spreadInput", "linkedInput", "mainLabel", "linkedLabel"];

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

        this.offcanvas.show();
    }

    // Calcul en temps réel dans le drawer (optionnel mais recommandé pour l'UX)
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
        if (!card) return;

        const display = card.querySelector('.h6');
        if (display) {
            display.textContent = value;
        }

        const btn = card.querySelector('[data-action="click->editor#open"]');
        if (btn) {
            btn.dataset.value = value;
        }
    }
}
