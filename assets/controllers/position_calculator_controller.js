import { Controller } from '@hotwired/stimulus';

/**
 * Le script calcule automatiquement la quantité en fonction du prix LVC et de la taille de la position.
 * Une date de validité par défaut est également calculée à trois mois à partir de la date actuelle.
 *
 * @property {String} hasValidityDateTarget
 * @property {String} validityDateTarget
 * @property {String} hasTargetLvcTarget
 * @property {String} hasTargetCacTarget
 * @property {String} buyLvcTarget
 * @property {String} buyCacTarget
 * @property {String} positionSizeTarget
 * @property {String} positionSizeTarget.value
 * @property {String} quantityTarget
 * @property {String} targetLvcTarget
 * @property {String} targetCacTarget
 * @property {String} buyLvcTarget.value
 * @property {String} buyCacTarget.value
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    // On définit les cibles. Stimulus les trouvera uniquement dans le drawer courant.
    static targets = ["buyLvc", "buyCac", "quantity", "targetCac", "targetLvc", "positionSize", "validityDate"];

    // Le hook 'connect' s'exécute au chargement du drawer
    connect() {
        if (this.hasValidityDateTarget && !this.validityDateTarget.value) {
            const date = new Date();
            // Ajout de 3 mois
            date.setMonth(date.getMonth() + 3);

            // Formatage YYYY-MM-DD requis par <input type="date">
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0'); // padStart garantit le 0 devant
            const dd = String(date.getDate()).padStart(2, '0');

            this.validityDateTarget.value = `${yyyy}-${mm}-${dd}`;
        }
    }

    // Calcul de la quantité et cibles
    calculate() {
        const positionSize = parseFloat(this.positionSizeTarget.value || 0);
        const buyPriceLvc = parseFloat(this.buyLvcTarget.value || 0);
        const buyPriceCac = parseFloat(this.buyCacTarget.value || 0);

        // Calcul Quantité
        if (positionSize > 0 && buyPriceLvc > 0) {
            this.quantityTarget.value = Math.floor(positionSize / buyPriceLvc);
        }

        // Calcul Cible LVC (+20%)
        if (buyPriceLvc > 0 && this.hasTargetLvcTarget) {
            this.targetLvcTarget.value = (buyPriceLvc * 1.20).toFixed(2);
        }

        // Calcul Cible CAC (+10%)
        if (buyPriceCac > 0 && this.hasTargetCacTarget) {
            this.targetCacTarget.value = Math.round(buyPriceCac * 1.10);
        }
    }
}
