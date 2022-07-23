declare global {
    interface Window {
        frontMenu: any;
        dropdown: any;
        Alpine: any;
        flashMessage: any;
        datagridFilter: any;
        photosModal: any;
        modal: any;
    }
}

//styles
import './scss/index.scss';

//scripts
import naja from 'naja';
import {registerExtensions} from "./ts/naja/extension";

naja.initialize();
registerExtensions(naja);

import './js/LiveFormValidation';

import './ts/alpine/AppAlpine';

import './ts/select/select';