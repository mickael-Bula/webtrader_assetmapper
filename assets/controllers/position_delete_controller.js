import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

/**
 * @property {String} button.dataset.positionDeleteUrl
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ['button'];

    confirmDelete(event) {
        // Le data-position-id est sur le bouton qui a déclenché l'événement
        const button = event.target.closest('[data-position-delete-url]');
        this.positionId = button?.dataset.positionId;
        this.deleteUrl = button?.dataset.positionDeleteUrl;
    }

    async executeDelete(event) {
        if (!this.deleteUrl) {
            return;
        }

        const modal = event.target.closest('.modal');
        const confirmButton = event.target;

        // Désactiver le bouton pendant la requête
        confirmButton.disabled = true;
        confirmButton.textContent = 'Suppression...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const response = await fetch(this.deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                // Fermer le modal
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();

                // Fermer le drawer de la position
                const drawer = document.querySelector(`#offcanvasPosition${this.positionId}`);
                if (drawer) {
                    const bsOffcanvas = bootstrap.Offcanvas.getInstance(drawer);
                    bsOffcanvas?.hide();
                }

                // Recharger la page pour mettre à jour l'affichage
                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.error || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors de la suppression');
        } finally {
            confirmButton.disabled = false;
            confirmButton.textContent = 'Supprimer';
        }
    }
}
