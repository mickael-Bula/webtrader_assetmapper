import { startStimulusApp } from '@symfony/stimulus-bundle';

// Exporte la variable app pour activer l'extension Stimulus Dev Tools.
export const app = startStimulusApp();

window.Stimulus = app;
