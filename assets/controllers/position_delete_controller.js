import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    // 1. Appelée au clic sur le bouton dans l'Offcanvas
    prepareDelete(event) {
        const triggerButton = event.currentTarget;
        const modalElement = document.getElementById('deletePositionModal');
        const confirmButton = modalElement.querySelector('#confirmDeleteModalBtn');

        // On injecte les infos dans le bouton de confirmation de la modale
        confirmButton.dataset.url = triggerButton.dataset.url;
        confirmButton.dataset.id = triggerButton.dataset.id;
        confirmButton.dataset.token = triggerButton.dataset.token;
    }

    // 2. Appelée au clic sur le bouton de la modale
    async executeDelete(event) {
        const confirmButton = event.currentTarget; // C'est le bouton de la modale
        const deleteUrl = confirmButton.dataset.url;
        const positionId = confirmButton.dataset.id;
        const csrfToken = confirmButton.dataset.token;

        if (!deleteUrl) {
            console.error("URL de suppression manquante !");
            return;
        }

        confirmButton.disabled = true;
        confirmButton.textContent = 'Suppression...';

        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                // Fermeture
                const modalElement = document.getElementById('deletePositionModal');
                bootstrap.Modal.getInstance(modalElement)?.hide();

                const drawer = document.querySelector(`#offcanvasPosition${positionId}`);
                bootstrap.Offcanvas.getInstance(drawer)?.hide();

                window.location.reload();
            } else {
                const data = await response.json();
                alert(data.error || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Une erreur est survenue');
        } finally {
            confirmButton.disabled = false;
            confirmButton.textContent = 'Supprimer';
        }
    }
}
