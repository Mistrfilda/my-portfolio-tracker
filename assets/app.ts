declare global {
    interface Window {
        frontMenu: any;
        dropdown: any;
        Alpine: any;
        flashMessage: any;
        datagridFilter: any;
        photosModal: any;
        modal: any;
        loadChart: any;
        expenseMainTag: any;
        addExpenseOtherTag: any;
        removeOtherTag: any;
        currencyConvert: any;
    }
}

//styles
import 'tom-select/dist/css/tom-select.default.css';
import './css/index.css';

import './js/LiveFormValidation';

import './ts/alpine/AppAlpine';

import './ts/select/select';

import '@tailwindplus/elements';
