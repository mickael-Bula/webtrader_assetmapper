import { Controller } from '@hotwired/stimulus';
import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';

// Enregistrement explicite des composants nécessaires
Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

/**
 * @property {String} colorValue
 * @property {Number} percentageValue
 * @property {Number} rotationValue
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static values = { color: String, percentage: Number, rotation: Number };

    connect() {
        new Chart(this.element, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [this.percentageValue, 100 - this.percentageValue],
                    backgroundColor: [this.colorValue, '#334155'],
                    borderWidth: 0,
                    circumference: 360,
                    cutout: '75%',
                }]
            },
            options: {
                rotation: this.rotationValue,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                animation: {
                    animateRotate: true,
                    animateScale: false,
                    duration: 1500, // Durée de l'animation pour un effet fluide
                    delay: 200,
                    easing: 'easeOutQuart'
                }
            }
        });
    }
}
