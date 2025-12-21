import { Controller } from '@hotwired/stimulus';

/**
 * @property {HTMLElement} buttonTarget
 * @property {string} onClassValue
 * @property {string} offClassValue
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["button"]

    // On définit les classes comme des valeurs pour pouvoir les changer depuis le HTML si besoin (flexibilité)
    static values = {
        onClass: { type: String, default: 'icon-on' },
        offClass: { type: String, default: 'icon-off' }
    }

    toggle() {
        const btn = this.buttonTarget;

        // Bascule entre les deux classes
        if (btn.classList.contains(this.offClassValue)) {
            btn.classList.replace(this.offClassValue, this.onClassValue);
        } else {
            btn.classList.replace(this.onClassValue, this.offClassValue);
        }
    }
}
