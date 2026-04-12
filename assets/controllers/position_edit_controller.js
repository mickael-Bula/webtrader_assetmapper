import { Controller } from '@hotwired/stimulus';
import * as bootstrap from 'bootstrap';

/**
 * @property {Number} positionIdValue
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static values = { positionId: Number };

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

                                if (data.position) {
                                    /**
                                     * @type {{buyPrice: string, targetPrice: string, lvcBuyPrice: string, lvcTargetPrice: string, quantity: string | null}} */
                                    const p = data.position;

                                    const drawer = document.getElementById(`offcanvasPosition${positionId}`);
                                    if (drawer) {
                                        const offcanvas = bootstrap.Offcanvas.getInstance(drawer);
                                        if (offcanvas) {
                                            offcanvas.hide();
                                        }

                                        const h5 = drawer.querySelector('.offcanvas-title');
                                        if (h5) {
                                            const entrypointText = h5.textContent.match(/Point d'entrée : ([\d\s,.]+) pts/);
                                            const rankText = h5.textContent.match(/Rang #(\d+)/);
                                            const ep = entrypointText ? entrypointText[1] : '';
                                            const rank = rankText ? rankText[1] : '';
                                            h5.textContent = `Point d'entrée : ${ep} pts - Rang #${rank}`;
                                        }

                                        const statusText = drawer.querySelector('.offcanvas-body .badge');
                                        if (statusText) {
                                            const status = statusText.textContent.trim().toLowerCase();
                                            statusText.className = `badge ${status === 'waiting' ? 'bg-warning text-dark' : 'bg-success'}`;
                                            statusText.textContent = status.toUpperCase();
                                        }

                                        const labels = drawer.querySelectorAll('.row .h5, .row .fw-bold');
                                        if (labels.length >= 5) {
                                            labels[0].textContent = `${parseFloat(p.buyPrice).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}`;
                                            labels[1].textContent = `${parseFloat(p.targetPrice).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}`;
                                            labels[2].textContent = `${parseFloat(p.lvcBuyPrice).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €`;
                                            labels[3].textContent = `${parseFloat(p.lvcTargetPrice).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €`;
                                            labels[4].textContent = `${p.quantity ?? '—'} titres`;
                                        }
                                    }

                                    const row = document.querySelector(`[data-bs-target="#offcanvasPosition${positionId}"]`)?.closest('tr');
                                    if (row) {
                                        const cells = row.querySelectorAll('td');
                                        cells[1].textContent = p.quantity ?? '—';
                                        cells[2].textContent = p.lvcBuyPrice ? parseFloat(p.lvcBuyPrice).toFixed(2) : '—';
                                        cells[3].textContent = p.buyPrice ? parseFloat(p.buyPrice).toFixed(0) : '—';
                                        cells[4].textContent = p.lvcTargetPrice ? parseFloat(p.lvcTargetPrice).toFixed(2) : '—';
                                        cells[5].textContent = p.targetPrice ? parseFloat(p.targetPrice).toFixed(0) : '—';
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
