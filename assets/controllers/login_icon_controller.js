import { Controller } from '@hotwired/stimulus';

/**
 * @property {HTMLButtonElement} buttonTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["button"]

    toggle(event) {
        // 1. Empêche toute action par défaut immédiate
        event.preventDefault();

        // 1. PHASE DE PRESSION (Immédiate)
        this.buttonTarget.classList.add('icon-off', 'is-logging-out');
        this.buttonTarget.style.transition = "transform 0.1s cubic-bezier(0.175, 0.885, 0.32, 1.275)";
        this.buttonTarget.style.transform = "scale(0.85)"; // Enfoncement

        // 2. PHASE DE RELÂCHEMENT / REBOND (Après 100ms)
        setTimeout(() => {
            // On augmente la durée à 0.5s pour ralentir le relâchement. La courbe de Bézier donne l'effet de rebond élastique à la fin.
            this.buttonTarget.style.transition = "transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease";
            this.buttonTarget.style.transform = "scale(1.25)";
            this.buttonTarget.style.opacity = "0.7";
        }, 300);

        // 3. REDIRECTION (Après 250ms au total)
        setTimeout(() => {
            window.location.href = '/logout';
        }, 300);
    }
}
