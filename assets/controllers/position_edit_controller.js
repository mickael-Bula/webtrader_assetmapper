import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

export default class extends Controller {
    static values = { positionId: Number };
    positionIdValue;

    async openModal() {
        const positionId = this.positionIdValue;

        try {
            const response = await fetch(`/position/${positionId}/edit`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();

            let modalContainer = document.getElementById('position-edit-modal-container');
            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'position-edit-modal-container';
                document.body.appendChild(modalContainer);
            }

            modalContainer.innerHTML = html;

            const modal = modalContainer.querySelector('.modal');
            if (modal) {
                const bsModal = new bootstrap.Modal(modal);

                const form = modal.querySelector('form');
                if (form) {
                    const actionUrl = form.getAttribute('action');
                    const match = actionUrl.match(/\/position\/(\d+)\/edit/);
                    const extractedId = match ? match[1] : positionId;

                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const submitBtn = form.querySelector('[type="submit"]');
                        const originalText = submitBtn.textContent;
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Enregistrement...';

                        const formData = new FormData(form);

                        try {
                            const submitResponse = await fetch(actionUrl, {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            });

                            if (submitResponse.ok) {
                                const data = await submitResponse.json();

                                if (data.drawerHtml) {
                                    const drawerId = `offcanvasPosition${extractedId}`;
                                    const drawerEl = document.getElementById(drawerId);

                                    if (drawerEl) {
                                        const offcanvas = bootstrap.Offcanvas.getInstance(drawerEl);
                                        if (offcanvas) {
                                            offcanvas.hide();
                                        }
                                        drawerEl.outerHTML = data.drawerHtml;
                                    }
                                }

                                if (data.position) {
                                    const row = document.querySelector(`[data-bs-target="#offcanvasPosition${extractedId}"]`)?.closest('tr');
                                    if (row) {
                                        const cells = row.querySelectorAll('td');
                                        cells[1].textContent = data.position.quantity ?? '—';
                                        cells[2].textContent = data.position.lvcBuyPrice ? parseFloat(data.position.lvcBuyPrice).toFixed(2) : '—';
                                        cells[3].textContent = data.position.buyPrice ? parseFloat(data.position.buyPrice).toFixed(0) : '—';
                                        cells[4].textContent = data.position.lvcTargetPrice ? parseFloat(data.position.lvcTargetPrice).toFixed(2) : '—';
                                        cells[5].textContent = data.position.targetPrice ? parseFloat(data.position.targetPrice).toFixed(0) : '—';
                                    }
                                }

                                bsModal.hide();
                            } else {
                                const errorHtml = await submitResponse.text();
                                const tempDiv = document.createElement('div');
                                tempDiv.innerHTML = errorHtml;
                                const newForm = tempDiv.querySelector('form');
                                if (newForm) {
                                    form.replaceWith(newForm);
                                }
                            }
                        } catch (error) {
                            console.error('Erreur lors de la modification:', error);
                        } finally {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }
                    });
                }

                bsModal.show();

                modal.addEventListener('hidden.bs.modal', () => {
                    modalContainer.innerHTML = '';
                }, { once: true });
            }
        } catch (error) {
            console.error('Erreur lors du chargement du formulaire:', error);
        }
    }
}
