import {Naja} from "naja";

export class SpinnerExtension {
    initialize(naja: Naja) {
        document.addEventListener('DOMContentLoaded', () => {
            const mainContent = document.querySelector('.spinner');
            naja.addEventListener(
                'start',
                () => {
                    mainContent.classList.remove('hidden');
                }
            );

            naja.addEventListener(
                'complete',
                () => {
                    mainContent.classList.add('hidden');
                }
            );
        });
    }
}
