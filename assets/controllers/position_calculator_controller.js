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
 * @property {Boolean} hasOperationDateTarget.value
 * @property {String} operationDateTarget.value
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    // On définit les cibles. Stimulus les trouvera uniquement dans le drawer courant.
    static targets = [
        "buyLvc",
        "buyCac",
        "quantity",
        "targetCac",
        "targetLvc",
        "positionSize",
        "operationDate",
        "validityDate"
    ];

    // Le hook 'connect' s'exécute au chargement du drawer
    connect() {
        // On ne calcule la date que si le target 'validityDate' est présent dans le HTML
        // Au chargement, si la date de validité n'est pas remplie, on l'initialise.
        if (this.hasValidityDateTarget && !this.validityDateTarget.value) {
            this.updateValidityDate();
        }
    }

    /**
     * Calcule la date de validité à +3 mois par rapport à la date de l'opération
     */
    updateValidityDate() {
        if (!this.hasValidityDateTarget) {
            return;
        }

        // On récupère la date de l'opération. Si elle est vide, on prend la date du jour.
        const baseDateStr = this.hasOperationDateTarget && this.operationDateTarget.value
            ? this.operationDateTarget.value
            : null;

        const date = baseDateStr ? new Date(baseDateStr) : new Date();

        // Sécurité : Si la date de l'opération est invalide
        if (isNaN(date.getTime())) {
            return;
        }

        // On ajoute 3 mois
        date.setMonth(date.getMonth() + 3);

        // Formatage en YYYY-MM-DD requis par l'input HTML5 date
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');

        this.validityDateTarget.value = `${yyyy}-${mm}-${dd}`;

        // On définit le "min" de la date de validité pour empêcher de choisir une date antérieure à l'opération.
        if (this.hasOperationDateTarget) {
            this.validityDateTarget.min = this.operationDateTarget.value;
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
