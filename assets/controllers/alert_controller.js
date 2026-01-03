import { Controller } from '@hotwired/stimulus';
import * as bootstrap from "bootstrap";

// noinspection JSUnusedGlobalSymbols
export default class extends Controller {
    connect() {
        // Cette méthode s'exécute dès que l'élément apparaît dans le DOM
        if (!this.element.classList.contains('alert-danger')) {
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
