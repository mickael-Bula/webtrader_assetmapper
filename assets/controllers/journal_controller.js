import { Controller } from '@hotwired/stimulus';

/**
 * @property {HTMLUListElement} listTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["list"];

    // Mapping des couleurs métiers
    actionStyles = {
        'buy': 'text-success',                  // Vert
        'sell': 'text-danger',                  // Rouge
        'trailing_adjustment': 'text-info',     // Bleu
        'pending_order_create': 'text-info',    // Bleu
        'pending_order_update': 'text-info',    // Bleu
        'position_cleanup': 'text-info',        // Bleu
        'setup': 'text-warning',                // Jaune
        'default': 'text-white text-opacity-50' // Gris
    };

    addEntry(message, action = 'default') {
        const entry = document.createElement('li');
        const colorClass = this.actionStyles[action] || this.actionStyles['default'];

        entry.className = `small ${colorClass} mb-1 border-bottom border-secondary border-opacity-25 pb-1`;
        entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;

        this.listTarget.prepend(entry);
    }
}
