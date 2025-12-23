import { Controller } from '@hotwired/stimulus';

/**
 * @property {string} openClass
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static classes = ["open"];

    // Bascule l'état du menu
    toggle() {
        document.body.classList.toggle(this.openClass);
    }

    // Ferme le menu (utilisé pour le clic sur le 'main/overlay')
    close() {
        if (document.body.classList.contains(this.openClass)) {
            document.body.classList.remove(this.openClass);
        }
    }
}
