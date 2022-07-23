import {Naja} from "naja";

export class SpinnerExtension {
    initialize(naja: Naja) {
        document.addEventListener('DOMContentLoaded', () => {
            const mainContent = document.querySelector('.spinner');
            naja.addEventListener(
                'start',
                () => {
                    mainContent.classList.remove('hidden');
                    mainContent.classList.add('fixed');
                }
            );

            naja.addEventListener(
                'complete',
                () => {
                    mainContent.classList.remove('fixed');
                    mainContent.classList.add('hidden');
                }
            );
        });
    }
}