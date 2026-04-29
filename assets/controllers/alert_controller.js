import { Controller } from '@hotwired/stimulus';
import * as bootstrap from "bootstrap";

// noinspection JSUnusedGlobalSymbols
export default class extends Controller {

    /**
     * Cette méthode s'exécute dès que l'élément apparaît dans le DOM
     */
    connect() {
        /** @type {HTMLElement} */
        const element = this.element;
        if (!element.classList.contains('alert-danger')) {
            setTimeout(() => {
                this.close();
            }, 5000);
        }
    }

    close() {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(this.element);
        if (bsAlert) {
            bsAlert.close();
        }
    }
}
