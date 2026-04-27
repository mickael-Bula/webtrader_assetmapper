import { Controller } from '@hotwired/stimulus';

/**
 * @property {HTMLUListElement} listTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["list"];

    // Mapping des couleurs métiers
    actionStyles = {
        'create': 'text-success', // Vert
        'update': 'text-info',    // Bleu
        'delete': 'text-danger',  // Rouge
        'default': 'text-muted'   // Gris
    };

    addEntry(message, action = 'default') {
        const entry = document.createElement('li');
        const colorClass = this.actionStyles[action] || this.actionStyles['default'];

        entry.className = `small ${colorClass} mb-1 border-bottom border-secondary border-opacity-25 pb-1`;
        entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;

        this.listTarget.prepend(entry);
    }
}
