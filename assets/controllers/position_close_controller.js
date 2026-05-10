import { Controller } from "@hotwired/stimulus";
import { ClosePositionCommand } from "close-position-command";

/**
 * @property {HTMLFormElement} lvcInputTarget
 * @property {HTMLFormElement} formTarget
 * @property {HTMLButtonElement} submitButtonTarget
 * @property {HTMLSpanElement} pnlDisplayTarget
 * @property {HTMLSpanElement} pnlPercentTarget
 * @property {HTMLSpanElement} buyPriceValue
 * @property {HTMLSpanElement} quantityValue
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["form", "lvcInput", "pnlDisplay", "pnlPercent", "submitButton"];
    static values = {
        buyPrice: Number,
        quantity: Number
    };

    /**
     * Calcule la plus-value en temps réel lors de la saisie
     */
    calculate() {
        const sellPrice = parseFloat(this.lvcInputTarget.value);
        const buyPrice = this.buyPriceValue;
        const qty = this.quantityValue;

        if (isNaN(sellPrice) || sellPrice <= 0) {
            this.resetDisplay();
            return;
        }

        const diff = sellPrice - buyPrice;
        const pnlEuros = diff * qty;
        const pnlPercent = (diff / buyPrice) * 100;

        // Mise à jour du texte
        this.pnlDisplayTarget.textContent = `${pnlEuros.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
        this.pnlPercentTarget.textContent = `${pnlPercent >= 0 ? '+' : ''}${pnlPercent.toFixed(2)} %`;

        // Mise à jour visuelle de la performance : vert pour plus-value, rouge pour baisse
        if (pnlEuros >= 0) {
            this.pnlDisplayTarget.className = "mb-0 fw-bold transition-all text-success";
            this.pnlPercentTarget.className = "badge bg-success px-2 py-1";
        } else {
            this.pnlDisplayTarget.className = "mb-0 fw-bold transition-all text-danger";
            this.pnlPercentTarget.className = "badge bg-danger px-2 py-1";
        }
    }

    /**
     * Envoi du formulaire en AJAX via le pattern Command
     */
    async submit(event) {
        event.preventDefault();

        // Récupération ds élements HTML
        const submitBtn = this.submitButtonTarget;
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>En cours...';

        // Préparation de la commande
        const command = new ClosePositionCommand(
            this.formTarget.action,
            new FormData(this.formTarget)
        );

        try {
            // Exécution de la commande
            const data = await command.execute();

            if (data.success) {
                // Succès : On recharge la page pour voir le mouvement dans le portefeuille
                window.location.reload();
            } else {
                alert(data.error || "Une erreur est survenue lors de la clôture.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        } catch (error) {
            console.error("Erreur:", error);
            alert("Erreur réseau ou serveur.");
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    resetDisplay() {
        this.pnlDisplayTarget.textContent = "0.00 €";
        this.pnlDisplayTarget.className = "mb-0 fw-bold transition-all text-secondary";
        this.pnlPercentTarget.textContent = "0.00 %";
        this.pnlPercentTarget.className = "badge bg-secondary px-2 py-1";
    }
}
