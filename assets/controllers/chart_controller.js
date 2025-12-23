import {Controller} from '@hotwired/stimulus';

/**
 * @property {HTMLImageElement} displayTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["display"];

    connect() {
        /** @type {HTMLElement} */
        const element = this.element;

        // Pre-loading pour la fluidité
        element.querySelectorAll('.tab-btn').forEach(btn => {
            const img = new Image();
            img.src = btn.dataset.url;
        });
    }
    switch(event) {
        // Pour que le lien ne recharge pas la page
        event.preventDefault();

        /** @type {HTMLElement} */
        const element = this.element;

        // On récupère l'URL de l'image stockée dans le bouton
        this.displayTarget.src = event.currentTarget.dataset.url;

        element.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        event.currentTarget.classList.add('active');
    }
}
