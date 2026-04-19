import { Controller } from '@hotwired/stimulus';

/**
 * @property {String} portfolioTarget
 * @property {String} positionSizeTarget
 * @property {String} ratioValTarget
 * @property {String} realRatioValTarget
 * @property {String} warningTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    // On définit les cibles (targets) pour retrouver les éléments facilement au moyen des datasets.
    static targets = ["portfolio", "positionSize", "ratioVal", "realRatioVal", "warning"];

    connect() {
        // Le code s'exécute automatiquement quand le controller est "attaché" à l'élément
        this.checkRisk();
    }

    // Méthode appelée lors de la saisie
    checkRisk() {
        const total = parseFloat(this.portfolioTarget.value);
        const size = parseFloat(this.positionSizeTarget.value);

        if (!isNaN(total) && !isNaN(size) && total > 0) {
            // 1. Calcul Ratio Max (1/10e)
            const tenPercent = (total / 10).toFixed(2);
            this.ratioValTarget.textContent = tenPercent + ' €';

            // 2. Calcul Ratio Réel en %
            const realRatio = (size / total) * 100;
            this.realRatioValTarget.textContent = realRatio.toFixed(2) + ' %';

            // Gestion visuelle
            if (size > (total / 10)) {
                this.warningTarget.classList.remove('d-none');
                this.realRatioValTarget.classList.remove('text-info');
                this.realRatioValTarget.classList.add('text-danger');
            } else {
                this.warningTarget.classList.add('d-none');
                this.realRatioValTarget.classList.remove('text-danger');
                this.realRatioValTarget.classList.add('text-info');
            }
        } else {
            this.ratioValTarget.textContent = '-- €';
            this.realRatioValTarget.textContent = '-- %';
        }
    }
}
