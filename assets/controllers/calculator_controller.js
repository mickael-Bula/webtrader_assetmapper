import { Controller } from '@hotwired/stimulus';

/**
 * @property {String} entrypointTarget
 * @property {String} spreadTarget
 * @property {String} simulationBodyTarget
 * @property {String} nextCycleTarget
 * @property {Number} lastCacValue
 * @property {Number} lastLvcValue
 * @property {String} cycleLabelTarget
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static targets = ["entrypoint", "spread", "simulationBody", "nextCycle", "cycleLabel"];
    static values = { lastCac: Number, lastLvc: Number };

    connect() {
        this.calculate();
    }

    calculate() {
        const startCac = parseFloat(this.entrypointTarget.value);
        const spread = parseFloat(this.spreadTarget.value);

        if (isNaN(startCac) || startCac <= 0) return;

        let html = '';
        let nextCycleVal = 0;

        // Simulation des 3 lignes basées sur l'index (0, 1, 2) * spread
        for (let i = 0; i < 3; i++) {
            const drop = i * spread;
            const cacTarget = startCac * (1 - drop);
            const cacExit = Math.round(cacTarget * 1.10);

            // Calcul LVC avec levier 2
            const varCac = (cacTarget / this.lastCacValue) - 1;
            const variationLvc = varCac * 2;
            const lvcPrice = this.lastLvcValue * (1 + variationLvc);

            html += `
                <tr class="border-bottom border-secondary border-opacity-25 text-center align-middle">
                    <td class="ps-3 py-3 text-start text-success fw-bold">#${i + 1}</td>
                    <td class="text-center">
                        <span class="badge bg-secondary bg-opacity-25 text-webtrader-muted border-0 small" style="width: 100px;">
                        En attente
                        </span>
                    </td>
                    <td class=" text-center py-3">
                        <div class="text-white fw-bold">${Math.round(cacTarget)} pts</div>
                        <div class="text-webtrader-muted small">${lvcPrice.toFixed(2)} €</div>
                    </td>
                    <td class=" text-end pe-3 py-3">
                        <div class="text-info fw-bold">${cacExit} pts</div>
                        <div class="text-webtrader-muted small">${(lvcPrice * 1.20).toFixed(2)} €</div>
                    </td>
                </tr>`;

            this.simulationBodyTarget.innerHTML = html;

            // Calcul dynamique du prochain cycle (Rang 3 * spread)
            const nextCycleFactor = 3 * spread;
            const nextCycleVal = Math.round(startCac * (1 - nextCycleFactor));
            this.nextCycleTarget.textContent = nextCycleVal + ' pts';

            // On convertit le facteur en pourcentage (ex: 0.06 -> "- 6%")
            this.cycleLabelTarget.textContent = ` (-${(nextCycleFactor * 100).toFixed(0)} %)`;
        }
    }
}
