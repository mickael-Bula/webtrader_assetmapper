import { Controller } from '@hotwired/stimulus';
import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';

// Enregistrement explicite des composants nécessaires
Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

/**
 * @property {Array} colorsValue
 * @property {Array} valuesValue
 * @property {Number} percentageValue
 * @property {Number} rotationValue
 * @property {Chart} chart
 */
// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    static values = {
        values: Array,  // ex: [25, 25, 0, 0, 50]
        colors: Array,  // ex: ["#0dcaf0", "#198754", "#ffc107", "#dc3545", "#334155"]
        rotation: Number
    };

    connect() {
        // 1. Initialiser l'Observer
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                // Si l'élément est visible à l'écran
                if (entry.isIntersecting) {
                    this.renderChart();
                    // Une fois affiché, on arrête d'observer pour économiser des ressources
                    this.observer.unobserve(this.element);
                }
            });
        }, {
            threshold: 0.1 // Déclenche quand 10% de l'élément est visible
        });

        // 2. Démarrer l'observation
        this.observer.observe(this.element);
    }

    renderChart() {
        // Stocker l'instance dans this.chart pour pouvoir la détruire plus tard
        this.chart = new Chart(this.element, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: this.valuesValue,
                    backgroundColor: this.colorsValue,
                    borderWidth: 0,
                    cutout: '75%',
                }]
            },
            options: {
                rotation: this.rotationValue,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                responsive: true,
                maintainAspectRatio: false,
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

    disconnect() {
        // Arrête l'observer et détruit le graphique
        if (this.observer) {
            this.observer.disconnect();
        }
        if (this.chart) {
            this.chart.destroy();
        }
    }
}
